<?php

namespace Tests\Unit;

use App\Services\Import\ColumnMappingService;
use App\Services\Standardization\FuzzyMatcherService;
use Tests\TestCase;

class ColumnMappingServiceTest extends TestCase
{
    protected ColumnMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ColumnMappingService(new FuzzyMatcherService);
    }

    public function test_exact_alias_mapping_for_qty_variants(): void
    {
        $headers = ['QTY', 'Price USD', 'Country', 'Year', 'INN'];
        $result = $this->service->detectMapping($headers);

        $this->assertSame(0, $result['mapped_headers']['quantity']);
        $this->assertContains('QTY', array_column($result['mappings'], 'header'));
    }

    public function test_fuzzy_mapping_for_tender_ref(): void
    {
        $headers = ['Tender Ref', 'Price USD', 'Country', 'Year', 'Product Name'];
        $result = $this->service->detectMapping($headers);

        $this->assertSame(0, $result['mapped_headers']['tender_number']);
    }

    public function test_extra_columns_identified(): void
    {
        $headers = ['Price USD', 'Country', 'Year', 'INN', 'Notes', 'Buyer'];
        $result = $this->service->detectMapping($headers);

        $this->assertContains('Notes', $result['extra_columns']);
        $this->assertContains('Buyer', $result['extra_columns']);
    }

    public function test_country_alias_mapping(): void
    {
        $headers = ['Nation', 'Price USD', 'Year', 'INN'];
        $result = $this->service->detectMapping($headers);

        $this->assertSame(0, $result['mapped_headers']['country']);
    }
}
