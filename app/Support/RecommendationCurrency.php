<?php

namespace App\Support;

use App\Models\Currency;

class RecommendationCurrency
{
    public const CODE = 'USD';

    public const LABEL = 'USD';

    protected static ?int $usdCurrencyId = null;

    public static function usdCurrencyId(): ?int
    {
        if (self::$usdCurrencyId !== null) {
            return self::$usdCurrencyId;
        }

        self::$usdCurrencyId = Currency::query()
            ->where('code', self::CODE)
            ->value('id');

        return self::$usdCurrencyId;
    }

    public static function format(mixed $amount, int $decimals = 2): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        return number_format((float) $amount, $decimals).' '.self::LABEL;
    }
}
