<?php

namespace App\Services\Materialization;

use App\Models\Country;
use App\Models\ImportRow;
use App\Models\Tender;
use App\Support\Normalization\TextNormalizer;

class TenderMaterializationService
{
    public function __construct(
        protected TextNormalizer $normalizer,
    ) {}

    /**
     * @return array{tender_id: int, created: bool}
     */
    public function resolve(ImportRow $row, int $countryId, ?MaterializationLookupCache $cache = null): array
    {
        $std = $row->normalized_data['standardization'] ?? [];
        $tenderStd = $std['tender'] ?? [];

        $tenderNumber = $tenderStd['tender_number']
            ?? $this->normalizer->normalizeTenderNumber($row->raw_tender_number);
        $year = $tenderStd['year'] ?? ($row->normalized_data['year'] ?? null);
        $version = $tenderStd['version'] ?? $this->normalizer->normalizeBasic($row->raw_version);

        if ($tenderNumber === null) {
            throw new \RuntimeException('Tender number is required for materialization.');
        }

        $year = $year !== null ? (int) $year : null;
        $version = $version ?: null;

        $cachedTenderId = $cache?->findTenderId($tenderNumber, $countryId, $year, $version);

        if ($cachedTenderId !== null) {
            return ['tender_id' => $cachedTenderId, 'created' => false];
        }

        $tender = Tender::query()
            ->where('tender_number', $tenderNumber)
            ->where('country_id', $countryId)
            ->when($year !== null, fn ($q) => $q->where('year', $year))
            ->when($year === null, fn ($q) => $q->whereNull('year'))
            ->when($version !== null, fn ($q) => $q->where('version', $version))
            ->when($version === null, fn ($q) => $q->whereNull('version'))
            ->first();

        if ($tender !== null) {
            $cache?->rememberTender($tenderNumber, $countryId, $year, $version, (int) $tender->id);

            return ['tender_id' => $tender->id, 'created' => false];
        }

        $country = $cache?->country($countryId) ?? Country::query()->find($countryId);
        $title = sprintf(
            '%s Tender %s - %s',
            $country?->name ?? 'Unknown',
            $tenderNumber,
            $year ?? 'N/A'
        );

        $tender = Tender::query()->create([
            'tender_number' => $tenderNumber,
            'country_id' => $countryId,
            'year' => $year,
            'version' => $version,
            'title' => $title,
            'status' => 'active',
            'metadata' => [
                'source_import_row_id' => $row->id,
                'import_batch_id' => $row->import_batch_id,
            ],
        ]);

        $cache?->rememberTender($tenderNumber, $countryId, $year, $version, (int) $tender->id);

        return ['tender_id' => $tender->id, 'created' => true];
    }
}
