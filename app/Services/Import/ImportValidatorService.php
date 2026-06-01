<?php

namespace App\Services\Import;

use App\Enums\ImportRowValidationStatus;

class ImportValidatorService
{
    /**
     * @param  array<string, mixed>  $canonical
     * @return array{
     *     validation_status: string,
     *     error_message: ?string,
     *     warning_messages: array<int, string>,
     *     normalized_data: array<string, mixed>,
     *     confidence_score: ?float
     * }
     */
    public function validate(array $canonical): array
    {
        $errors = [];
        $warnings = [];
        $normalized = $this->buildNormalizedData($canonical);

        if (! $this->hasDrugIdentity($canonical)) {
            $errors[] = 'At least one drug identity field is required (Code, INN, or Product Name).';
        }

        if (! $this->hasValue($canonical['country'] ?? null)) {
            $errors[] = 'Country is required.';
        }

        if (! $this->hasValue($canonical['year'] ?? null)) {
            $errors[] = 'Year is required.';
        } elseif ($normalized['year'] === null) {
            $errors[] = 'Year must be a valid numeric year.';
        }

        $priceUsd = $normalized['price_usd'] ?? null;

        if ($priceUsd === null) {
            $errors[] = 'Price USD is required and must be numeric and greater than zero.';
        } elseif ($priceUsd <= 0) {
            $errors[] = 'Price USD must be greater than zero.';
        }

        if (! $this->hasValue($canonical['quantity'] ?? $canonical['qty'] ?? null)) {
            $warnings[] = 'Quantity is missing or invalid.';
        }

        if (! $this->hasValue($canonical['tender_number'] ?? null)) {
            $warnings[] = 'Tender # is missing.';
        }

        if (! $this->hasValue($canonical['company_name'] ?? null) && ! $this->hasValue($canonical['winner'] ?? null)) {
            $warnings[] = 'Both Company Name and Winner are missing.';
        }

        if (! $this->hasValue($canonical['tender_value'] ?? null)) {
            $warnings[] = 'Tender Value is missing.';
        }

        if (! $this->hasValue($canonical['awarded_price'] ?? null)) {
            $warnings[] = 'Awarded price is missing.';
        }

        $status = ImportRowValidationStatus::Valid->value;
        $errorMessage = null;

        if ($errors !== []) {
            $status = ImportRowValidationStatus::Invalid->value;
            $errorMessage = implode(' ', $errors);
        } elseif ($warnings !== []) {
            $status = ImportRowValidationStatus::Warning->value;
        }

        $confidence = $this->calculateConfidenceScore($status, count($warnings));

        return [
            'validation_status' => $status,
            'error_message' => $errorMessage,
            'warning_messages' => $warnings,
            'normalized_data' => $normalized,
            'confidence_score' => $confidence,
        ];
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    protected function hasDrugIdentity(array $canonical): bool
    {
        return $this->hasValue($canonical['code'] ?? null)
            || $this->hasValue($canonical['inn'] ?? null)
            || $this->hasValue($canonical['product_name'] ?? null);
    }

    protected function hasValue(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || $this->isFormulaPlaceholder($trimmed)) {
            return false;
        }

        return true;
    }

    /**
     * Excel formula placeholders (=, =ROUND(...), etc.) are not usable field values.
     * Calculated values from OpenSpout arrive without a leading "=".
     */
    protected function isFormulaPlaceholder(string $value): bool
    {
        if ($value === '=') {
            return true;
        }

        return str_starts_with($value, '=');
    }

    /**
     * @param  array<string, mixed>  $canonical
     * @return array<string, mixed>
     */
    protected function buildNormalizedData(array $canonical): array
    {
        return [
            'code' => $this->normalizeText($canonical['code'] ?? null),
            'inn' => $this->normalizeText($canonical['inn'] ?? null),
            'product_name' => $this->normalizeText($canonical['product_name'] ?? null),
            'country' => $this->normalizeText($canonical['country'] ?? null),
            'tender_number' => $this->normalizeText($canonical['tender_number'] ?? null),
            'awarded_price' => $this->parseNumber($canonical['awarded_price'] ?? null),
            'price_usd' => $this->parseNumber($canonical['price_usd'] ?? null),
            'winner' => $this->normalizeText($canonical['winner'] ?? null),
            'company_name' => $this->normalizeText($canonical['company_name'] ?? null),
            'version' => $this->normalizeText($canonical['version'] ?? null),
            'year' => $this->parseYear($canonical['year'] ?? null),
            'qty' => $this->parseNumber($canonical['quantity'] ?? $canonical['qty'] ?? null),
            'tender_value' => $this->parseNumber($canonical['tender_value'] ?? null),
        ];
    }

    protected function normalizeText(?string $value): ?string
    {
        if (! $this->hasValue($value)) {
            return null;
        }

        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    protected function parseNumber(?string $value): ?float
    {
        if (! $this->hasValue($value)) {
            return null;
        }

        $clean = str_replace([',', ' '], '', $value);
        $clean = preg_replace('/[^0-9.\-]/', '', $clean ?? '') ?? '';

        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    protected function parseYear(?string $value): ?int
    {
        if (! $this->hasValue($value)) {
            return null;
        }

        if (preg_match('/(19|20)\d{2}/', $value, $matches)) {
            return (int) $matches[0];
        }

        if (is_numeric($value)) {
            $year = (int) $value;

            return ($year >= 1900 && $year <= 2100) ? $year : null;
        }

        return null;
    }

    protected function calculateConfidenceScore(string $status, int $warningCount): ?float
    {
        if ($status === ImportRowValidationStatus::Invalid->value) {
            return 0;
        }

        $score = 100 - ($warningCount * 8);

        return max(0, min(100, (float) $score));
    }
}
