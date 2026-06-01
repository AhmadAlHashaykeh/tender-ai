<?php

namespace App\Services\Tender;

use App\Models\Country;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Support\Normalization\TextNormalizer;
use Illuminate\Support\Facades\DB;

class UpcomingTenderService
{
    public function __construct(
        protected TextNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Tender
    {
        return DB::transaction(function () use ($data): Tender {
            $countryId = $this->resolveCountryId((string) ($data['country'] ?? ''));

            $tender = Tender::query()->create([
                'tender_number' => $this->normalizer->normalizeTenderNumber((string) $data['tender_number']),
                'country_id' => $countryId,
                'year' => (int) $data['year'],
                'version' => $this->normalizer->normalizeBasic($data['version'] ?? null),
                'title' => trim((string) $data['tender_name']),
                'status' => 'upcoming',
                'metadata' => [
                    'entry_mode' => 'upcoming_tender',
                    'notes' => $data['notes'] ?? null,
                    'expected_closing_date' => $data['expected_closing_date'] ?? null,
                    'authority' => $data['authority'] ?? null,
                    'category' => $data['category'] ?? null,
                    'expected_inn' => $data['expected_inn'] ?? null,
                    'expected_code' => $data['expected_code'] ?? null,
                    'expected_product_name' => $data['expected_product_name'] ?? null,
                ],
            ]);

            $standardizedDrugId = $this->resolveStandardizedDrugId($data);

            TenderItem::query()->create([
                'tender_id' => $tender->id,
                'standardized_drug_id' => $standardizedDrugId,
                'quantity' => isset($data['expected_qty']) && $data['expected_qty'] !== ''
                    ? (float) $data['expected_qty']
                    : null,
                'description' => $data['expected_product_name'] ?? $data['expected_inn'] ?? null,
                'metadata' => [
                    'expected_inn' => $data['expected_inn'] ?? null,
                    'expected_code' => $data['expected_code'] ?? null,
                    'expected_product_name' => $data['expected_product_name'] ?? null,
                    'is_upcoming' => true,
                ],
            ]);

            return $tender->load('country');
        });
    }

    protected function resolveCountryId(string $rawCountry): int
    {
        $normalized = $this->normalizer->normalizeCountry($rawCountry);

        if ($normalized === null) {
            throw new \InvalidArgumentException('Country could not be resolved.');
        }

        $countries = Country::query()->where('is_active', true)->get();

        foreach ($countries as $country) {
            if (mb_strtolower($country->name) === $normalized) {
                return $country->id;
            }

            foreach ([$country->code, $country->iso_code_2, $country->iso_code_3] as $code) {
                if ($code !== null && mb_strtolower((string) $code) === $normalized) {
                    return $country->id;
                }
            }
        }

        throw new \InvalidArgumentException("Country \"{$rawCountry}\" was not found in reference data.");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveStandardizedDrugId(array $data): ?int
    {
        $code = $this->normalizer->normalizeDrugCode($data['expected_code'] ?? null);

        if ($code !== null) {
            $byCode = StandardizedDrug::query()
                ->where('is_active', true)
                ->whereRaw('UPPER(code) = ?', [$code])
                ->first();

            if ($byCode !== null) {
                return $byCode->id;
            }
        }

        $inn = $this->normalizer->normalizeDrugInn($data['expected_inn'] ?? null);

        if ($inn !== null) {
            $byInn = StandardizedDrug::query()
                ->where('is_active', true)
                ->whereRaw('LOWER(inn) = ?', [$inn])
                ->first();

            if ($byInn !== null) {
                return $byInn->id;
            }
        }

        return null;
    }
}
