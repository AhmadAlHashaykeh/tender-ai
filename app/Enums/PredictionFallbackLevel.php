<?php

namespace App\Enums;

enum PredictionFallbackLevel: string
{
    case Country = 'country';
    case Region = 'region';
    case Global = 'global';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Country => 'Country-Level Data',
            self::Region => 'Regional Data',
            self::Global => 'Global Market Data',
            self::None => 'No market data',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Country => 'Pricing uses statistics from the selected country.',
            self::Region => 'Country-level data was unavailable; regional market statistics were used.',
            self::Global => 'Country and regional data were unavailable; global market statistics were used.',
            self::None => 'No usable market statistics were found.',
        };
    }
}
