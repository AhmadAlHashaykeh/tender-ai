<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, SettingSeeder::class]);
        $this->user = User::factory()->create();
    }

    public function test_documentation_page_loads_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->get(route('internal.documentation'))
            ->assertOk()
            ->assertSee('TenderAI System Documentation')
            ->assertSee('Data Pipeline Flow')
            ->assertSee('Current Production Status')
            ->assertDontSee('DB_PASSWORD')
            ->assertDontSee('sk_live_');
    }

    public function test_guest_cannot_access_documentation(): void
    {
        $this->get(route('internal.documentation'))->assertRedirect('/login');
    }
}
