<?php

namespace Tests\Unit;

use App\Models\Tender;
use App\Services\Tender\TenderGroupKeyService;
use Tests\TestCase;

class TenderGroupKeyServiceTest extends TestCase
{
    protected TenderGroupKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenderGroupKeyService::class);
    }

    public function test_kimadia_variants_normalize_to_same_group_key(): void
    {
        $cases = [
            ['title' => 'Iraq Tender KIMADIA-2023', 'tender_number' => 'KIMADIA-2023'],
            ['title' => 'KIMADIA 2024', 'tender_number' => 'KIMADIA-2024'],
            ['title' => 'Iraq Tender KIMADIA-2025', 'tender_number' => 'KIMADIA-2025'],
        ];

        $keys = array_map(
            fn (array $case) => $this->service->deriveFromLabels($case['title'], $case['tender_number']),
            $cases,
        );

        $this->assertSame(['KIMADIA', 'KIMADIA', 'KIMADIA'], $keys);
    }

    public function test_ghc_and_gcc_programs_normalize_distinctly(): void
    {
        $this->assertSame('GHC', $this->service->deriveFromLabels('GHC 2023', 'GHC-2023'));
        $this->assertSame('GCC', $this->service->deriveFromLabels('GCC Tender 2024', 'GCC-2024'));
    }

    public function test_unrelated_tender_names_do_not_over_normalize(): void
    {
        $this->assertSame(
            'NUPCO_MEDICAL_SUPPLIES',
            $this->service->deriveFromLabels('NUPCO Medical Supplies 2024', 'NUPCO-MED-2024'),
        );
        $this->assertSame(
            'MOH_CARDIO',
            $this->service->deriveFromLabels('MOH Cardio Tender 2023', 'MOH-CARDIO-2023'),
        );
        $this->assertNotSame(
            $this->service->deriveFromLabels('NUPCO Medical Supplies 2024', 'NUPCO-MED-2024'),
            $this->service->deriveFromLabels('MOH Cardio Tender 2023', 'MOH-CARDIO-2023'),
        );
    }

    public function test_derive_from_tender_model(): void
    {
        $tender = new Tender([
            'title' => 'Iraq Tender KIMADIA-2024',
            'tender_number' => 'KIMADIA-2024',
        ]);

        $this->assertSame('KIMADIA', $this->service->deriveFromTender($tender));
    }
}
