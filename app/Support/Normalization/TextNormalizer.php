<?php

namespace App\Support\Normalization;

class TextNormalizer
{
    /** @var array<string, string> */
    protected const COUNTRY_ALIASES = [];

    public function countryAliases(): array
    {
        return array_merge(
            self::COUNTRY_ALIASES,
            config('import.country_aliases', [])
        );
    }

    /** @var list<string> */
    protected const LEGAL_SUFFIXES = [
        'pharmaceutical',
        'pharma',
        'company',
        'ltd',
        'llc',
        'inc',
        'co',
    ];

    /** @var array<string, string> */
    protected const FORM_ALIASES = [
        'tab' => 'tablet',
        'tabs' => 'tablet',
        'tablets' => 'tablet',
        'caps' => 'capsule',
        'capsules' => 'capsule',
        'inj' => 'injection',
        'vials' => 'vial',
    ];

    public function trim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function normalizeBasic(?string $value): ?string
    {
        $value = $this->trim($value);

        if ($value === null) {
            return null;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $this->removeSafePunctuation($value);
    }

    public function normalizeCompanyName(?string $value): ?string
    {
        $normalized = $this->normalizeBasic($value);

        if ($normalized === null) {
            return null;
        }

        foreach (self::LEGAL_SUFFIXES as $suffix) {
            $pattern = '/\b'.preg_quote($suffix, '/').'\b\.?/u';
            $normalized = preg_replace($pattern, '', $normalized) ?? $normalized;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized;

        return $normalized === '' ? null : $normalized;
    }

    public function normalizeCountry(?string $value): ?string
    {
        $normalized = $this->normalizeBasic($value);

        if ($normalized === null) {
            return null;
        }

        return $this->countryAliases()[$normalized] ?? $normalized;
    }

    public function normalizeTenderNumber(?string $value): ?string
    {
        $value = $this->trim($value);

        if ($value === null) {
            return null;
        }

        $value = mb_strtoupper($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    public function normalizeDrugCode(?string $value): ?string
    {
        $value = $this->trim($value);

        if ($value === null) {
            return null;
        }

        return mb_strtoupper(preg_replace('/\s+/', '', $value) ?? $value);
    }

    public function normalizeDrugInn(?string $value): ?string
    {
        return $this->normalizeBasic($value);
    }

    public function normalizeDrugProductName(?string $value): ?string
    {
        return $this->normalizeBasic($value);
    }

    /**
     * @return array{
     *     product_name: ?string,
     *     strength: ?string,
     *     strength_unit: ?string,
     *     form: ?string
     * }
     */
    public function extractDrugComponents(?string $productName): array
    {
        $normalized = $this->normalizeDrugProductName($productName);

        if ($normalized === null) {
            return [
                'product_name' => null,
                'strength' => null,
                'strength_unit' => null,
                'form' => null,
            ];
        }

        $strength = null;
        $strengthUnit = null;
        $form = null;

        if (preg_match('/(\d+(?:\.\d+)?)\s*(mg|g|ml)\b/i', $normalized, $matches)) {
            $strength = $matches[1];
            $strengthUnit = mb_strtolower($matches[2]);
        }

        $formPatterns = [
            'injection' => 'injection',
            'tablet' => 'tablet',
            'capsule' => 'capsule',
            'vial' => 'vial',
            'syrup' => 'syrup',
        ];

        foreach ($formPatterns as $pattern => $canonical) {
            if (preg_match('/\b'.preg_quote($pattern, '/').'\b/', $normalized)) {
                $form = $canonical;
                break;
            }
        }

        if ($form === null && preg_match('/\b(tab|tabs|tablets|caps|capsules|inj|vials)\b/', $normalized, $formMatch)) {
            $form = self::FORM_ALIASES[$formMatch[1]] ?? $formMatch[1];
        }

        return [
            'product_name' => $normalized,
            'strength' => $strength,
            'strength_unit' => $strengthUnit,
            'form' => $form,
        ];
    }

    protected function removeSafePunctuation(string $value): string
    {
        $value = preg_replace('/[.,;:!\'"()\/\\\\-]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
