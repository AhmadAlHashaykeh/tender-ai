<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, SettingSeeder::class]);
        $this->user = User::factory()->create();
    }

    public function test_settings_page_loads_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('General Preferences')
            ->assertSee('AI Configuration')
            ->assertDontSee('sk_live_');
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->get(route('settings.index'))->assertRedirect('/login');
    }

    public function test_general_settings_update_persists_values(): void
    {
        $this->actingAs($this->user)
            ->put(route('settings.general.update'), [
                'organization_name' => 'Acme Pharma',
                'default_currency' => 'USD',
                'date_format' => 'd/m/Y',
                'rows_per_page' => 50,
                'timezone' => 'Europe/London',
                'language' => 'en',
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'general']))
            ->assertSessionHas('success');

        $service = app(SettingsService::class);

        $this->assertSame('Acme Pharma', $service->getString('general.organization_name'));
        $this->assertSame('USD', $service->getString('general.default_currency'));
        $this->assertSame(50, $service->getInteger('general.rows_per_page'));
        $this->assertSame('Europe/London', $service->getString('general.timezone'));
    }

    public function test_prediction_settings_update_persists_values(): void
    {
        $this->actingAs($this->user)
            ->put(route('settings.prediction.update'), [
                'calculation_model_version' => 'v1.1',
                'backend_only_confidence_threshold' => 75,
                'trend_adjustment_cap' => 5,
                'aggressive_discount_percent' => 4,
                'conservative_premium_percent' => 4,
                'large_quantity_multiplier' => 2.5,
                'large_quantity_discount_percent' => 2,
                'small_quantity_multiplier' => 0.4,
                'small_quantity_premium_percent' => 2,
            ])
            ->assertSessionHasNoErrors();

        $service = app(SettingsService::class);

        $this->assertSame('v1.1', $service->getString('prediction.calculation_model_version'));
        $this->assertSame(75, $service->getInteger('prediction.backend_only_confidence_threshold'));
        $this->assertEqualsWithDelta(5.0, $service->getFloat('prediction.trend_adjustment_cap'), 0.001);
    }

    public function test_standardization_settings_update_persists_thresholds(): void
    {
        $this->actingAs($this->user)
            ->put(route('settings.standardization.update'), [
                'drug_auto_approve_min' => 90,
                'company_auto_approve_min' => 88,
                'row_auto_approve_min' => 70,
                'ai_auto_approve_min' => 82,
                'fuzzy_auto_approve_min' => 79,
                'max_ai_calls_per_batch' => 25,
                'enable_ai_assist' => true,
            ])
            ->assertSessionHasNoErrors();

        $service = app(SettingsService::class);

        $this->assertSame(90, $service->getInteger('standardization.drug_auto_approve_min'));
        $this->assertTrue($service->getBoolean('standardization.enable_ai_assist'));
    }

    public function test_ai_settings_stores_api_key_encrypted(): void
    {
        $this->actingAs($this->user)
            ->put(route('settings.ai.update'), [
                'provider' => 'openai',
                'default_model' => 'gpt-4o-mini',
                'advanced_model' => 'gpt-4o',
                'api_key' => 'sk-test-secret-key-12345',
                'temperature' => 0.3,
                'max_tokens' => 500,
                'timeout_seconds' => 45,
                'enable_narrative' => false,
                'narrative_min_confidence' => 50,
                'enable_standardization_assist' => false,
                'rate_limit_per_user_per_hour' => 20,
                'monthly_token_budget' => null,
                'system_prompt_version' => 'v1.0',
            ])
            ->assertSessionHasNoErrors();

        $setting = Setting::query()->where('key', 'ai.api_key')->first();

        $this->assertNotNull($setting);
        $this->assertSame('encrypted', $setting->type);
        $this->assertNotSame('sk-test-secret-key-12345', $setting->value);
        $this->assertSame('sk-test-secret-key-12345', Crypt::decryptString($setting->value));
    }

    public function test_ai_settings_page_does_not_expose_raw_api_key(): void
    {
        app(SettingsService::class)->setEncrypted('ai.api_key', 'sk-supersecretvalue');

        $response = $this->actingAs($this->user)->get(route('settings.index', ['tab' => 'ai']));

        $response->assertOk();
        $response->assertDontSee('sk-supersecretvalue');
        $response->assertSee('sk-****');
    }

    public function test_empty_api_key_field_keeps_existing_key(): void
    {
        app(SettingsService::class)->setEncrypted('ai.api_key', 'sk-keep-me');

        $this->actingAs($this->user)
            ->put(route('settings.ai.update'), [
                'provider' => 'openai',
                'default_model' => 'gpt-4o-mini',
                'api_key' => '',
                'temperature' => 0.2,
                'max_tokens' => 800,
                'timeout_seconds' => 60,
                'enable_narrative' => false,
                'narrative_min_confidence' => 50,
                'enable_standardization_assist' => false,
                'rate_limit_per_user_per_hour' => 10,
                'system_prompt_version' => 'v1.0',
            ]);

        $this->assertSame('sk-keep-me', app(SettingsService::class)->getEncrypted('ai.api_key'));
    }

    public function test_api_key_removal_works(): void
    {
        app(SettingsService::class)->setEncrypted('ai.api_key', 'sk-remove-me');

        $this->actingAs($this->user)
            ->delete(route('settings.ai.api-key.destroy'))
            ->assertRedirect(route('settings.index', ['tab' => 'ai']));

        $this->assertNull(app(SettingsService::class)->getEncrypted('ai.api_key'));
    }

    public function test_users_list_shows_real_users(): void
    {
        User::factory()->create(['name' => 'Real User', 'email' => 'real@example.com']);

        $this->actingAs($this->user)
            ->get(route('settings.index', ['tab' => 'users']))
            ->assertOk()
            ->assertSee('Real User')
            ->assertSee('real@example.com');
    }

    public function test_validation_errors_for_invalid_standardization_threshold(): void
    {
        $this->actingAs($this->user)
            ->from(route('settings.index', ['tab' => 'standardization']))
            ->put(route('settings.standardization.update'), [
                'drug_auto_approve_min' => 150,
                'company_auto_approve_min' => 85,
                'row_auto_approve_min' => 75,
                'ai_auto_approve_min' => 85,
                'fuzzy_auto_approve_min' => 80,
                'max_ai_calls_per_batch' => 50,
                'enable_ai_assist' => false,
            ])
            ->assertSessionHasErrors('drug_auto_approve_min');
    }

    public function test_standardization_service_reads_thresholds_from_settings(): void
    {
        app(SettingsService::class)->updateGroup('standardization', [
            'drug_auto_approve_min' => ['value' => 99, 'type' => 'integer'],
            'company_auto_approve_min' => ['value' => 99, 'type' => 'integer'],
            'row_auto_approve_min' => ['value' => 99, 'type' => 'integer'],
        ]);

        $service = app(\App\Services\Standardization\ImportRowStandardizationService::class);
        $reflection = new \ReflectionClass($service);

        $drugMin = $reflection->getMethod('drugAutoMin');
        $drugMin->setAccessible(true);

        $this->assertSame(99.0, $drugMin->invoke($service));
    }

    public function test_prediction_services_read_scenario_settings(): void
    {
        app(SettingsService::class)->updateGroup('prediction', [
            'aggressive_discount_percent' => ['value' => 10, 'type' => 'float'],
            'conservative_premium_percent' => ['value' => 10, 'type' => 'float'],
        ]);

        $settings = app(SettingsService::class);

        $this->assertEqualsWithDelta(10.0, $settings->getFloat('prediction.aggressive_discount_percent'), 0.001);
    }
}
