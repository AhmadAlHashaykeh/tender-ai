<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Services\Materialization\ImportMaterializationService;
use App\Services\Materialization\MaterializationChunkService;

class ImportPipelineService
{
    public function __construct(
        protected ImportMaterializationService $materializationService,
        protected ImportBatchStatsService $batchStatsService,
    ) {}

    /**
     * @return array{
     *     current_stage: string,
     *     steps: list<array{key: string, label: string, status: string, detail: string}>,
     *     row_counts: array<string, int>,
     *     standardization: array<string, mixed>,
     *     can_run_standardization: bool,
     *     can_retry_standardization: bool,
     *     is_standardization_running: bool,
     *     can_review: bool,
     *     can_materialize: bool,
     *     is_materialization_running: bool,
     *     can_retry_materialization: bool,
     *     materialization: array<string, mixed>,
     *     is_complete: bool
     * }
     */
    public function state(ImportBatch $batch): array
    {
        $rowCounts = $this->batchStatsService->rowCounts($batch->id, $batch);
        $materialization = $this->materializationService->batchMaterializationStats($batch);
        $standardization = $this->standardizationProgress($batch);
        $materializationProgress = $this->materializationProgress($batch);

        $validationComplete = in_array($batch->status, [
            ImportBatchStatus::Completed->value,
            ImportBatchStatus::CompletedWithErrors->value,
        ], true);

        $current = $this->resolveCurrentStage($batch, $validationComplete, $rowCounts, $materialization, $standardization);

        $stageDefinitions = [
            ['key' => 'validation', 'label' => 'Validation'],
            ['key' => 'standardization', 'label' => 'Standardization'],
            ['key' => 'review', 'label' => 'Review'],
            ['key' => 'materialization', 'label' => 'Materialization'],
        ];

        $userExperience = $this->userExperienceState($batch, $validationComplete, $rowCounts, $standardization, $materializationProgress);

        $stageOrder = array_column($stageDefinitions, 'key');
        $currentIndex = $current === 'complete'
            ? count($stageOrder)
            : array_search($current, $stageOrder, true);

        $steps = [];

        foreach ($stageDefinitions as $index => $stage) {
            if ($batch->status === ImportBatchStatus::Failed->value) {
                $status = $stage['key'] === 'validation' ? 'failed' : 'upcoming';
            } elseif ($current === 'complete') {
                $status = 'completed';
            } elseif ($index < $currentIndex) {
                $status = 'completed';
            } elseif ($index === $currentIndex) {
                $status = 'current';
            } else {
                $status = 'upcoming';
            }

            $steps[] = array_merge($stage, [
                'status' => $status,
                'detail' => $this->stageDetail($stage['key'], $batch, $rowCounts, $materialization, $validationComplete, $standardization, $materializationProgress),
            ]);
        }

        $isRunning = $standardization['status'] === 'processing';
        $isMaterializationRunning = in_array($materializationProgress['status'], ['preparing', 'processing'], true);

        return [
            'current_stage' => $current,
            'steps' => $steps,
            'row_counts' => $rowCounts,
            'standardization' => $standardization,
            'can_run_standardization' => $validationComplete
                && $rowCounts['standardization_pending'] > 0
                && ! $isRunning
                && ! in_array($standardization['status'], ['processing'], true),
            'can_retry_standardization' => ($standardization['status'] === 'failed'
                    || ($standardization['failed_chunks'] ?? 0) > 0)
                && ! $isRunning
                && ($rowCounts['standardization_pending'] > 0 || ($standardization['failed_chunks'] ?? 0) > 0),
            'is_standardization_running' => $isRunning,
            'can_review' => ! $isRunning && $rowCounts['standardization_review'] > 0,
            'can_materialize' => ! $isRunning
                && ! $isMaterializationRunning
                && $materialization['eligible_pending'] > 0,
            'is_materialization_running' => $isMaterializationRunning,
            'can_retry_materialization' => ($materializationProgress['status'] === 'failed'
                    || ($materializationProgress['failed_chunks'] ?? 0) > 0)
                && ! $isMaterializationRunning
                && $batch->usesChunkedMaterialization(),
            'materialization' => $materializationProgress,
            'is_complete' => $current === 'complete',
            'user_experience' => $userExperience,
        ];
    }

    /**
     * Simplified SaaS-facing pipeline state (non-technical labels).
     *
     * @param  array<string, int>  $rowCounts
     * @param  array<string, int>  $materialization
     * @return array{
     *     current_step: string,
     *     steps: list<array{key: string, label: string, status: string}>,
     *     headline: string,
     *     subline: string,
     *     primary_action: ?array{label: string, route: string, type: string},
     *     is_ready: bool,
     *     is_preparing: bool,
     *     review_count: int,
     *     matched_products: int,
     *     ignored_records: int
     * }
     */
    public function userExperienceState(
        ImportBatch $batch,
        bool $validationComplete,
        array $rowCounts,
        array $standardization,
        array $materializationProgress,
    ): array {
        $metadata = $batch->metadata ?? [];
        $reviewCount = $rowCounts['standardization_review'] ?? 0;
        $readiness = app(ImportPipelineReadinessService::class);
        $isReady = $readiness->batchIsPipelineReady($batch);
        $statisticsFailed = ($metadata['statistics_status'] ?? '') === 'failed';
        $statisticsProcessing = ($metadata['statistics_status'] ?? '') === 'processing';
        $materializationComplete = ($metadata['materialization_status'] ?? '') === 'completed';

        $importInProgress = in_array($batch->status, [
            ImportBatchStatus::Queued->value,
            ImportBatchStatus::Processing->value,
            ImportBatchStatus::Parsing->value,
            ImportBatchStatus::Validating->value,
        ], true);

        $matchingInProgress = $validationComplete
            && in_array($standardization['status'] ?? '', ['processing', 'not_started'], true)
            && (($rowCounts['standardization_pending'] ?? 0) > 0
                || ($standardization['status'] ?? '') === 'processing');

        $materializationActive = in_array($materializationProgress['status'] ?? '', ['preparing', 'processing'], true);

        $preparingMarketData = $materializationActive;

        $buildingIntelligence = $statisticsProcessing
            || ($materializationComplete
                && $reviewCount === 0
                && ! $isReady
                && ! $statisticsFailed
                && ($metadata['statistics_status'] ?? 'not_started') === 'not_started'
                && $readiness->batchRequiresMarketStatistics($batch));

        $currentStep = match (true) {
            $isReady => 'ready',
            $statisticsFailed => 'intelligence',
            ! $validationComplete || $importInProgress => 'upload',
            $reviewCount > 0 => 'review',
            $buildingIntelligence => 'intelligence',
            $preparingMarketData => 'prepare',
            $matchingInProgress || ($standardization['status'] ?? '') === 'processing' => 'matching',
            $materializationComplete && ! $isReady => 'intelligence',
            default => 'upload',
        };

        $stepDefinitions = [
            ['key' => 'upload', 'label' => 'Uploading Data'],
            ['key' => 'matching', 'label' => 'Matching Products'],
            ['key' => 'prepare', 'label' => 'Preparing Market Data'],
            ['key' => 'intelligence', 'label' => 'Building Price Intelligence'],
            ['key' => 'ready', 'label' => 'Ready for Predictions'],
        ];

        if ($reviewCount > 0) {
            array_splice($stepDefinitions, 2, 0, [['key' => 'review', 'label' => 'Review Matches']]);
        }

        $stepKeys = array_column($stepDefinitions, 'key');
        $currentIndex = array_search($currentStep, $stepKeys, true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $steps = [];
        foreach ($stepDefinitions as $index => $definition) {
            $status = match (true) {
                $index < $currentIndex => 'completed',
                $index === $currentIndex => 'current',
                default => 'upcoming',
            };

            if ($definition['key'] === 'review' && $reviewCount === 0) {
                $status = 'completed';
            }

            $steps[] = array_merge($definition, ['status' => $status]);
        }

        $headline = match (true) {
            $isReady => 'Your data is ready.',
            $statisticsFailed => 'Market analysis preparation failed.',
            $importInProgress => 'Preparing your data...',
            $matchingInProgress || ($standardization['status'] ?? '') === 'processing' => 'Matching products...',
            $preparingMarketData => 'Preparing market data...',
            $buildingIntelligence || $statisticsProcessing => 'Building price intelligence...',
            $reviewCount > 0 => sprintf(
                '%s %s need your review before completion',
                number_format($reviewCount),
                $reviewCount === 1 ? 'item' : 'items',
            ),
            default => 'Processing your upload...',
        };

        $primaryAction = match (true) {
            $isReady => [
                'label' => 'Start Predictions',
                'route' => route('predictions.index'),
                'type' => 'link',
            ],
            $reviewCount > 0 => [
                'label' => 'Review Matches',
                'route' => route('standardization.index', ['batch' => $batch->id, 'status' => 'review_required']),
                'type' => 'link',
            ],
            $statisticsFailed, $buildingIntelligence, $statisticsProcessing => [
                'label' => 'Retry Market Statistics',
                'route' => route('imports.statistics.retry', $batch),
                'type' => 'form',
            ],
            $materializationComplete && ! $isReady => [
                'label' => 'Run Pending Processing',
                'route' => route('imports.pipeline.run-pending', $batch),
                'type' => 'form',
            ],
            default => null,
        };

        $matched = (int) ($rowCounts['materializable'] ?? 0);

        $ignored = ($rowCounts['invalid'] ?? 0)
            + ($rowCounts['standardization_rejected'] ?? 0)
            + ($rowCounts['standardization_skipped'] ?? 0);

        $pipelineStartedAt = $metadata['materialization_started_at']
            ?? $metadata['standardization_started_at']
            ?? $batch->started_at?->toIso8601String();

        $showLongWaitHint = (! $isReady && ! $statisticsFailed)
            && $pipelineStartedAt !== null
            && now()->parse($pipelineStartedAt)->lte(now()->subMinutes(2));

        return [
            'current_step' => $currentStep,
            'steps' => $steps,
            'headline' => $headline,
            'subline' => match (true) {
                $isReady => 'You can start predictions and explore recommendations.',
                $statisticsFailed => $metadata['statistics_last_error'] ?? 'Retry market statistics to continue.',
                default => 'We are preparing your file automatically. You can leave this page and return later.',
            },
            'primary_action' => $primaryAction,
            'is_ready' => $isReady,
            'is_preparing' => ! $isReady && ! $statisticsFailed,
            'show_long_wait_hint' => $showLongWaitHint,
            'statistics_failed' => $statisticsFailed,
            'statistics_processing' => $statisticsProcessing,
            'review_count' => $reviewCount,
            'matched_products' => $matched,
            'ignored_records' => $ignored,
        ];
    }

    /**
     * @param  array<string, int>  $rowCounts
     * @param  array<string, int>  $materialization
     */
    protected function resolveCurrentStage(
        ImportBatch $batch,
        bool $validationComplete,
        array $rowCounts,
        array $materialization,
        array $standardization,
    ): string {
        if ($batch->status === ImportBatchStatus::Failed->value) {
            return 'validation';
        }

        if (! $validationComplete) {
            return 'validation';
        }

        if ($standardization['status'] === 'processing' || $rowCounts['standardization_pending'] > 0) {
            return 'standardization';
        }

        if ($rowCounts['standardization_review'] > 0) {
            return 'review';
        }

        $metadata = $batch->metadata ?? [];

        if (in_array($metadata['materialization_status'] ?? '', ['preparing', 'processing'], true)) {
            return 'materialization';
        }

        if ($materialization['eligible_pending'] > 0) {
            return 'materialization';
        }

        if ($rowCounts['total'] === 0) {
            return 'complete';
        }

        if ($materialization['materialized'] > 0) {
            return 'complete';
        }

        if ($rowCounts['materializable'] === 0) {
            return 'complete';
        }

        return 'materialization';
    }

    /**
     * @param  array<string, int>  $rowCounts
     * @param  array<string, int>  $materialization
     */
    protected function stageDetail(
        string $stage,
        ImportBatch $batch,
        array $rowCounts,
        array $materialization,
        bool $validationComplete,
        array $standardization,
        array $materializationProgress,
    ): string {
        return match ($stage) {
            'validation' => $validationComplete
                ? sprintf('%d valid · %d warnings · %d invalid', $rowCounts['valid'], $rowCounts['warning'], $rowCounts['invalid'])
                : (in_array($batch->status, [
                    ImportBatchStatus::Queued->value,
                    ImportBatchStatus::Processing->value,
                ], true)
                    ? 'Import running in background — refresh for live progress'
                    : 'Parsing and validating uploaded rows'),
            'standardization' => $standardization['status'] === 'processing'
                ? sprintf(
                    'In progress · %d / %d rows · %d / %d chunks',
                    $standardization['processed'],
                    max(1, $standardization['total']),
                    $standardization['completed_chunks'] ?? 0,
                    max(1, $standardization['total_chunks'] ?? 0),
                )
                : ($standardization['status'] === 'completed' && is_array($standardization['summary'])
                    ? sprintf(
                        'Completed · auto-approved: %d · review: %d · rejected: %d',
                        $standardization['summary']['auto_approved'] ?? 0,
                        $standardization['summary']['review_required'] ?? 0,
                        $standardization['summary']['rejected'] ?? 0,
                    )
                    : sprintf(
                        '%d pending · %d auto-approved · %d skipped',
                        $rowCounts['standardization_pending'],
                        $rowCounts['standardization_auto_approved'],
                        $rowCounts['standardization_skipped'],
                    )),
            'review' => sprintf(
                '%d awaiting approval',
                $rowCounts['standardization_review'],
            ),
            'materialization' => $standardization['status'] === 'processing'
                ? 'Waiting for standardization'
                : (in_array($materializationProgress['status'], ['preparing', 'processing'], true)
                    ? sprintf(
                        'In progress · %d / %d rows · %d / %d chunks',
                        $materializationProgress['processed_rows'],
                        max(1, $materializationProgress['total_rows']),
                        $materializationProgress['completed_chunks'] ?? 0,
                        max(1, $materializationProgress['total_chunks'] ?? 0),
                    )
                    : ($materializationProgress['status'] === 'completed' && is_array($materializationProgress['summary'])
                        ? sprintf(
                            'Completed · materialized: %d · skipped: %d · failed: %d',
                            $materializationProgress['summary']['materialized'] ?? 0,
                            $materializationProgress['summary']['skipped'] ?? 0,
                            $materializationProgress['summary']['failed'] ?? 0,
                        )
                        : sprintf(
                            '%d materialized · %d ready · %d ineligible',
                            $materialization['materialized'],
                            $materialization['eligible_pending'],
                            $materialization['ineligible'],
                        ))),
            default => '',
        };
    }

    /**
     * @return array{
     *     status: string,
     *     processed: int,
     *     total: int,
     *     failed_rows: int,
     *     last_error: ?string,
     *     summary: ?array<string, int>,
     *     started_at: ?string,
     *     completed_at: ?string
     * }
     */
    public function standardizationProgress(ImportBatch $batch): array
    {
        $metadata = $batch->metadata ?? [];
        $summary = $metadata['standardization_summary'] ?? null;

        return [
            'status' => $metadata['standardization_status'] ?? 'not_started',
            'processed' => (int) ($metadata['standardization_processed_rows'] ?? 0),
            'total' => (int) ($metadata['standardization_total_rows'] ?? 0),
            'failed_rows' => (int) ($metadata['standardization_failed_rows'] ?? 0),
            'completed_chunks' => (int) ($metadata['standardization_completed_chunks'] ?? 0),
            'total_chunks' => (int) ($metadata['standardization_total_chunks'] ?? 0),
            'failed_chunks' => (int) ($metadata['standardization_failed_chunks'] ?? 0),
            'last_error' => $metadata['standardization_last_error'] ?? null,
            'summary' => $summary,
            'started_at' => $metadata['standardization_started_at'] ?? null,
            'completed_at' => $metadata['standardization_completed_at'] ?? null,
            'auto_approved' => (int) ($summary['auto_approved'] ?? $metadata['auto_approved_rows'] ?? 0),
            'review_required' => (int) ($summary['review_required'] ?? $metadata['standardization_review_rows'] ?? 0),
            'rejected' => (int) ($summary['rejected'] ?? $metadata['standardization_rejected_rows'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function materializationProgress(ImportBatch $batch): array
    {
        return app(MaterializationChunkService::class)->materializationProgress($batch);
    }
}
