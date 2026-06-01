<?php

namespace Tests\Unit;

use App\Support\RecommendationCurrency;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
    }

    public function test_format_appends_usd_label(): void
    {
        $this->assertSame('1.25 USD', RecommendationCurrency::format(1.25));
        $this->assertSame('12.50 USD', RecommendationCurrency::format(12.5, 2));
        $this->assertSame('12.5000 USD', RecommendationCurrency::format(12.5, 4));
    }

    public function test_usd_currency_id_resolves_from_database(): void
    {
        $this->assertNotNull(RecommendationCurrency::usdCurrencyId());
    }
}
