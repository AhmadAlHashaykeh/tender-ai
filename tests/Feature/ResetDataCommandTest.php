<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Setting;
use App\Models\User;
use App\Services\DataManagement\OperationalDataResetService;
use App\Services\Dev\TenderAiTestDataService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
            SettingSeeder::class,
        ]);
    }

    public function test_command_requires_confirmation_without_force(): void
    {
        $this->seedBusinessData();

        $this->artisan('tenderai:reset-data')
            ->expectsConfirmation('Create a backup and delete all business data?', false)
            ->assertFailed();

        $this->assertGreaterThan(0, ImportBatch::query()->count());
    }

    public function test_command_clears_business_data_and_preserves_users_and_settings(): void
    {
        $user = User::factory()->create();
        $this->seedBusinessData($user);

        $this->assertGreaterThan(0, ImportBatch::query()->count());
        $this->assertGreaterThan(0, Setting::query()->count());

        $this->artisan('tenderai:reset-data', ['--force' => true, '--no-cache-clear' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Tables cleared')
            ->expectsOutputToContain('all 0 rows')
            ->expectsOutputToContain('Import data: CLEAN')
            ->expectsOutputToContain('Jobs: CLEAN')
            ->expectsOutputToContain('Ready for fresh Excel import testing');

        foreach (OperationalDataResetService::BUSINESS_TABLES as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $this->assertDatabaseCount($table, 0);
        }

        foreach (OperationalDataResetService::QUEUE_TABLES as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $this->assertDatabaseCount($table, 0);
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertGreaterThan(0, Setting::query()->count());
        $this->assertGreaterThan(0, \App\Models\Country::query()->count());
    }

    public function test_command_reports_no_op_when_business_tables_are_empty(): void
    {
        User::factory()->create();

        $this->artisan('tenderai:reset-data', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No business or queue data found');
    }

    protected function seedBusinessData(?User $user = null): void
    {
        $user ??= User::factory()->create();
        $service = app(TenderAiTestDataService::class);

        $service->seedReferenceEntities();
        $service->seedImportBatchAndRows($user);
    }
}
