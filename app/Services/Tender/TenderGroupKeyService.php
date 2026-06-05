<?php

namespace App\Services\Tender;

use App\Models\Tender;

class TenderGroupKeyService
{
    /**
     * Country / market prefixes commonly prepended to tender program names.
     *
     * @var list<string>
     */
    protected const COUNTRY_PREFIXES = [
        'iraq',
        'saudi arabia',
        'saudi',
        'ksa',
        'kuwait',
        'uae',
        'emirates',
        'qatar',
        'bahrain',
        'oman',
        'gcc',
        'jordan',
        'lebanon',
        'egypt',
        'morocco',
        'tunisia',
        'algeria',
    ];

    public function deriveFromTender(Tender $tender): string
    {
        return $this->deriveFromLabels($tender->title, $tender->tender_number);
    }

    public function deriveFromLabels(?string $title, ?string $tenderNumber): string
    {
        $candidates = [];

        foreach ([$tenderNumber, $title] as $source) {
            $normalized = $this->normalizeLabel($source);
            if ($normalized !== null) {
                $candidates[$normalized] = strlen($normalized);
            }
        }

        if ($candidates === []) {
            $fallback = $this->normalizeLabel($tenderNumber) ?? $this->normalizeLabel($title) ?? 'UNKNOWN';

            return $this->toGroupKey($fallback);
        }

        uasort($candidates, fn (int $a, int $b) => $b <=> $a);

        return $this->toGroupKey((string) array_key_first($candidates));
    }

    public function displayName(string $groupKey): string
    {
        $label = str_replace('_', ' ', $groupKey);

        if (preg_match('/^[A-Z0-9]{2,12}$/', $groupKey)) {
            return $groupKey;
        }

        return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
    }

    public function normalizeLabel(?string $text): ?string
    {
        if (! filled($text)) {
            return null;
        }

        $text = trim((string) $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        foreach (self::COUNTRY_PREFIXES as $prefix) {
            $pattern = '/\b'.preg_quote($prefix, '/').'\s+tender\s*/iu';
            $text = preg_replace($pattern, '', $text) ?? $text;
        }

        $text = preg_replace('/\btender\s+(?:for\s+)?/iu', '', $text) ?? $text;
        $text = preg_replace('/\btender\b/iu', '', $text) ?? $text;
        $text = preg_replace('/[-\s_]+(19|20)\d{2}\b/u', '', $text) ?? $text;
        $text = preg_replace('/\b(19|20)\d{2}\b/u', '', $text) ?? $text;
        $text = preg_replace('/[-\s_]+$/u', '', $text) ?? $text;
        $text = preg_replace('/^[-\s_]+/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text, " -_/");

        if ($text === '' || mb_strlen($text) < 2) {
            return null;
        }

        return $text;
    }

    protected function toGroupKey(string $label): string
    {
        $key = preg_replace('/[^A-Za-z0-9]+/u', '_', $label) ?? $label;
        $key = trim($key, '_');

        return strtoupper($key !== '' ? $key : 'UNKNOWN');
    }
}
