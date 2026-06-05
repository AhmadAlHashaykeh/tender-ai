<?php

namespace App\Enums;

enum PredictionFallbackLevel: string
{
    case TenderGroup = 'tender_group';
    case Country = 'country';
    case Region = 'region';
    case Global = 'global';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::TenderGroup => 'Tender Program Data',
            self::Country => 'Country-Level Data',
            self::Region => 'Regional Data',
            self::Global => 'Global Market Data',
            self::None => 'No market data',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TenderGroup => 'Pricing uses historical awards from the selected tender program.',
            self::Country => 'Tender program data was unavailable; country-level market statistics were used.',
            self::Region => 'Tender program and country data were unavailable; regional market statistics were used.',
            self::Global => 'Tender program, country, and regional data were unavailable; global market statistics were used.',
            self::None => 'No usable market statistics were found.',
        };
    }
}
