<?php

namespace App\Services\AI;

use App\Models\AiUsageLog;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OpenAIService
{
    protected const CHAT_COMPLETIONS_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        protected SettingsService $settings,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $options = []): array
    {
        $provider = $this->settings->getString('ai.provider', 'openai');

        if ($provider !== 'openai') {
            return $this->failure('unsupported_provider', 'Configured AI provider is not OpenAI.', $options);
        }

        $apiKey = $this->settings->getEncrypted('ai.api_key');

        if (! filled($apiKey)) {
            return $this->failure('missing_api_key', 'OpenAI API key is not configured.', $options);
        }

        $model = (string) ($options['model'] ?? $this->settings->getString('ai.default_model', 'gpt-4o-mini'));
        $timeout = max(5, min((int) ($options['timeout'] ?? $this->settings->getInteger('ai.timeout_seconds', 60)), 120));
        $maxTokens = max(1, min((int) ($options['max_tokens'] ?? $this->settings->getInteger('ai.max_tokens', 800)), 2000));
        $temperature = max(0, min((float) ($options['temperature'] ?? $this->settings->getFloat('ai.temperature', 0.2)), 2));

        $sanitizedMessages = $this->sanitizeMessages($messages);
        $requestHash = hash('sha256', json_encode([
            'model' => $model,
            'messages' => $sanitizedMessages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ]));

        $startedAt = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->connectTimeout(min($timeout, 10))
                ->retry(1, 250, null, false)
                ->post(self::CHAT_COMPLETIONS_URL, [
                    'model' => $model,
                    'messages' => $sanitizedMessages,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]);

            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            if (! $response->successful()) {
                $errorCode = (string) ($response->json('error.code') ?? 'api_error');
                $message = (string) ($response->json('error.message') ?? $response->reason() ?? 'OpenAI API request failed.');

                $this->logUsage($options, [
                    'model' => $model,
                    'status' => 'failure',
                    'latency_ms' => $latencyMs,
                    'request_hash' => $requestHash,
                    'metadata' => [
                        'error_code' => $errorCode,
                        'http_status' => $response->status(),
                    ],
                ]);

                Log::warning('OpenAI request failed', [
                    'feature' => $options['feature'] ?? null,
                    'model' => $model,
                    'status' => $response->status(),
                    'error_code' => $errorCode,
                ]);

                return [
                    'success' => false,
                    'error_code' => $errorCode,
                    'message' => Str::limit($message, 240),
                    'model' => $model,
                    'response_time_ms' => $latencyMs,
                ];
            }

            $content = trim((string) ($response->json('choices.0.message.content') ?? ''));

            if ($content === '') {
                $this->logUsage($options, [
                    'model' => $model,
                    'status' => 'failure',
                    'latency_ms' => $latencyMs,
                    'request_hash' => $requestHash,
                    'metadata' => ['error_code' => 'empty_response'],
                ]);

                return [
                    'success' => false,
                    'error_code' => 'empty_response',
                    'message' => 'OpenAI returned an empty response.',
                    'model' => $model,
                    'response_time_ms' => $latencyMs,
                ];
            }

            $usage = [
                'prompt_tokens' => $response->json('usage.prompt_tokens'),
                'completion_tokens' => $response->json('usage.completion_tokens'),
                'total_tokens' => $response->json('usage.total_tokens'),
            ];

            $this->logUsage($options, [
                'model' => $model,
                'status' => 'success',
                'latency_ms' => $latencyMs,
                'request_hash' => $requestHash,
                'usage' => $usage,
                'metadata' => ['finish_reason' => $response->json('choices.0.finish_reason')],
            ]);

            return [
                'success' => true,
                'content' => $content,
                'tokens_used' => $usage['total_tokens'],
                'prompt_tokens' => $usage['prompt_tokens'],
                'completion_tokens' => $usage['completion_tokens'],
                'model' => $model,
                'response_time_ms' => $latencyMs,
            ];
        } catch (ConnectionException $exception) {
            return $this->handledException($exception, 'timeout_or_connection_error', $model, $requestHash, $startedAt, $options);
        } catch (Throwable $exception) {
            return $this->handledException($exception, 'openai_request_exception', $model, $requestHash, $startedAt, $options);
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return list<array{role: string, content: string}>
     */
    protected function sanitizeMessages(array $messages): array
    {
        return collect($messages)
            ->map(function (array $message): array {
                $role = in_array($message['role'] ?? '', ['system', 'user', 'assistant'], true)
                    ? $message['role']
                    : 'user';

                return [
                    'role' => $role,
                    'content' => Str::limit($this->sanitizeText((string) ($message['content'] ?? '')), 12000, ''),
                ];
            })
            ->values()
            ->all();
    }

    protected function sanitizeText(string $text): string
    {
        return trim((string) preg_replace('/[^\P{C}\t\n\r]+/u', '', $text));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function failure(string $code, string $message, array $options): array
    {
        $this->logUsage($options, [
            'model' => $this->settings->getString('ai.default_model', 'gpt-4o-mini'),
            'status' => 'failure',
            'latency_ms' => 0,
            'request_hash' => null,
            'metadata' => ['error_code' => $code],
        ]);

        return [
            'success' => false,
            'error_code' => $code,
            'message' => $message,
            'model' => $this->settings->getString('ai.default_model', 'gpt-4o-mini'),
            'response_time_ms' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function handledException(
        Throwable $exception,
        string $code,
        string $model,
        ?string $requestHash,
        float $startedAt,
        array $options,
    ): array {
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->logUsage($options, [
            'model' => $model,
            'status' => 'failure',
            'latency_ms' => $latencyMs,
            'request_hash' => $requestHash,
            'metadata' => [
                'error_code' => $code,
                'exception' => class_basename($exception),
            ],
        ]);

        Log::warning('OpenAI request exception', [
            'feature' => $options['feature'] ?? null,
            'model' => $model,
            'error_code' => $code,
            'exception' => class_basename($exception),
        ]);

        return [
            'success' => false,
            'error_code' => $code,
            'message' => 'OpenAI request failed safely.',
            'model' => $model,
            'response_time_ms' => $latencyMs,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $data
     */
    protected function logUsage(array $options, array $data): void
    {
        try {
            $usage = $data['usage'] ?? [];
            $totalTokens = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null;

            AiUsageLog::query()->create([
                'user_id' => $options['user_id'] ?? null,
                'prediction_id' => $options['prediction_id'] ?? null,
                'feature' => $options['feature'] ?? 'openai_chat',
                'provider' => 'openai',
                'model' => $data['model'] ?? null,
                'prompt_tokens' => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
                'completion_tokens' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
                'total_tokens' => $totalTokens,
                'estimated_cost_usd' => $this->estimateCost((string) ($data['model'] ?? ''), $totalTokens),
                'request_hash' => $data['request_hash'] ?? null,
                'status' => $data['status'] ?? 'failure',
                'latency_ms' => $data['latency_ms'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);
        } catch (Throwable $exception) {
            Log::warning('AI usage logging failed', [
                'feature' => $options['feature'] ?? null,
                'exception' => class_basename($exception),
            ]);
        }
    }

    protected function estimateCost(string $model, ?int $totalTokens): ?float
    {
        if ($totalTokens === null || $totalTokens <= 0) {
            return null;
        }

        $perMillion = match (true) {
            str_contains($model, 'gpt-4o-mini') => 0.60,
            str_contains($model, 'gpt-4o') => 5.00,
            default => null,
        };

        return $perMillion === null ? null : round(($totalTokens / 1_000_000) * $perMillion, 6);
    }
}
