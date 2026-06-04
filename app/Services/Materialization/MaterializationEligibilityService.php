<?php

namespace App\Services\Materialization;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\BidRecord;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MaterializationEligibilityService
{
    public const REASON_INVALID_VALIDATION = 'invalid_validation_status';

    public const REASON_INVALID_STANDARDIZATION = 'invalid_standardization_status';

    public const REASON_ALREADY_MATERIALIZED = 'already_materialized';

    public const REASON_INVALID_PRICE_USD = 'invalid_price_usd';

    public const REASON_MISSING_COUNTRY = 'missing_country';

    public const REASON_REGION_REQUIRES_COUNTRY = 'region_requires_country';

    public const REASON_MISSING_DRUG_IDENTITY = 'missing_drug_identity';

    public const REASON_MISSING_COMPANY = 'missing_company';

    public const REASON_MISSING_TENDER_NUMBER = 'missing_tender_number';

    /**
     * @return list<string>
     */
    public static function reasonLabels(): array
    {
        return [
            self::REASON_INVALID_VALIDATION => 'Invalid validation status',
            self::REASON_INVALID_STANDARDIZATION => 'Invalid standardization status',
            self::REASON_ALREADY_MATERIALIZED => 'Already materialized',
            self::REASON_INVALID_PRICE_USD => 'Invalid or missing price USD',
            self::REASON_MISSING_COUNTRY => 'Missing country',
            self::REASON_REGION_REQUIRES_COUNTRY => 'Regional tender — country required',
            self::REASON_MISSING_DRUG_IDENTITY => 'Missing drug identity',
            self::REASON_MISSING_COMPANY => 'Missing company or winner',
            self::REASON_MISSING_TENDER_NUMBER => 'Missing tender number',
        ];
    }

    public function isEligible(ImportRow $row): bool
    {
        return $this->ineligibilityReason($row) === null;
    }

    public function ineligibilityReason(ImportRow $row): ?string
    {
        if (! in_array($row->validation_status, [
            ImportRowValidationStatus::Valid->value,
            ImportRowValidationStatus::Warning->value,
        ], true)) {
            return self::REASON_INVALID_VALIDATION;
        }

        if (! in_array($row->standardization_status, [
            StandardizationStatus::AutoApproved->value,
            StandardizationStatus::Approved->value,
        ], true)) {
            return self::REASON_INVALID_STANDARDIZATION;
        }

        if ($this->isAlreadyMaterialized($row)) {
            return self::REASON_ALREADY_MATERIALIZED;
        }

        $priceUsd = $row->normalized_data['price_usd'] ?? null;
        if (! is_numeric($priceUsd) || (float) $priceUsd <= 0) {
            return self::REASON_INVALID_PRICE_USD;
        }

        if ($this->resolveCountryId($row) === null) {
            if ($this->resolveRegionId($row) !== null) {
                return self::REASON_REGION_REQUIRES_COUNTRY;
            }

            return self::REASON_MISSING_COUNTRY;
        }

        if (! $this->hasDrugIdentity($row)) {
            return self::REASON_MISSING_DRUG_IDENTITY;
        }

        if (! $this->hasCompanyIdentity($row)) {
            return self::REASON_MISSING_COMPANY;
        }

        if ($this->resolveTenderNumber($row) === null) {
            return self::REASON_MISSING_TENDER_NUMBER;
        }

        return null;
    }

    /**
     * @return array{reason: string, details: string}
     */
    public function skipPayload(ImportRow $row): array
    {
        $reason = $this->ineligibilityReason($row) ?? 'unknown';

        return [
            'reason' => $reason,
            'details' => self::reasonLabels()[$reason] ?? $reason,
        ];
    }

    public function isAlreadyMaterialized(ImportRow $row): bool
    {
        if ($row->bid_record_id !== null) {
            return true;
        }

        return BidRecord::query()->where('source_import_row_id', $row->id)->exists();
    }

    public function resolveCountryId(ImportRow $row): ?int
    {
        $countryId = $row->normalized_data['country_id'] ?? null;

        if ($countryId === null || $countryId === '') {
            return null;
        }

        return (int) $countryId;
    }

    public function resolveRegionId(ImportRow $row): ?int
    {
        $regionId = $row->normalized_data['region_id']
            ?? $row->normalized_data['standardization']['country']['region_id']
            ?? null;

        if ($regionId === null || $regionId === '') {
            return null;
        }

        return (int) $regionId;
    }

    public function resolveTenderNumber(ImportRow $row): ?string
    {
        $candidates = [
            $row->normalized_data['standardization']['tender']['tender_number'] ?? null,
            $row->raw_tender_number,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $trimmed = trim((string) $candidate);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * @param  Builder<ImportRow>  $query
     * @return Builder<ImportRow>
     */
    public function constrainEligible(Builder $query): Builder
    {
        $valid = ImportRowValidationStatus::Valid->value;
        $warning = ImportRowValidationStatus::Warning->value;
        $auto = StandardizationStatus::AutoApproved->value;
        $approved = StandardizationStatus::Approved->value;

        $priceUsdExpr = $this->jsonNumericExpression('price_usd');
        $countryIdExpr = $this->jsonPathExpression('country_id');
        $tenderStdExpr = $this->jsonPathExpression('standardization.tender.tender_number');

        return $query
            ->whereIn('validation_status', [$valid, $warning])
            ->whereIn('standardization_status', [$auto, $approved])
            ->whereNull('bid_record_id')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('bid_records')
                    ->whereColumn('bid_records.source_import_row_id', 'import_rows.id');
            })
            ->whereRaw("{$priceUsdExpr} > 0")
            ->whereRaw("{$countryIdExpr} IS NOT NULL")
            ->whereRaw("TRIM(COALESCE({$countryIdExpr}, '')) <> ''")
            ->where(function ($inner) {
                $inner->whereRaw("TRIM(COALESCE(raw_code, '')) <> ''")
                    ->orWhereRaw("TRIM(COALESCE(raw_inn, '')) <> ''")
                    ->orWhereRaw("TRIM(COALESCE(raw_product_name, '')) <> ''");
            })
            ->where(function ($inner) {
                $inner->whereRaw("TRIM(COALESCE(raw_company_name, '')) <> ''")
                    ->orWhereRaw("TRIM(COALESCE(raw_winner, '')) <> ''");
            })
            ->where(function ($inner) use ($tenderStdExpr) {
                $inner->whereRaw("TRIM(COALESCE(raw_tender_number, '')) <> ''")
                    ->orWhereRaw("TRIM(COALESCE({$tenderStdExpr}, '')) <> ''");
            });
    }

    protected function hasDrugIdentity(ImportRow $row): bool
    {
        return $this->filledTrim($row->raw_code)
            || $this->filledTrim($row->raw_inn)
            || $this->filledTrim($row->raw_product_name);
    }

    protected function hasCompanyIdentity(ImportRow $row): bool
    {
        return $this->filledTrim($row->raw_company_name)
            || $this->filledTrim($row->raw_winner);
    }

    protected function filledTrim(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    protected function jsonPathExpression(string $path): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "json_extract(normalized_data, '$.{$path}')",
            default => "JSON_UNQUOTE(JSON_EXTRACT(normalized_data, '$.{$path}'))",
        };
    }

    protected function jsonNumericExpression(string $path): string
    {
        $extract = $this->jsonPathExpression($path);
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "CAST({$extract} AS REAL)",
            default => "CAST({$extract} AS DECIMAL(20,6))",
        };
    }
}
