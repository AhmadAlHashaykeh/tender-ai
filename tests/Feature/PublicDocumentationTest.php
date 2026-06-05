<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, SettingSeeder::class]);
    }

    public function test_public_documentation_page_is_accessible_without_login(): void
    {
        $this->get(route('public.documentation'))
            ->assertOk()
            ->assertSee('TenderAI Documentation')
            ->assertSee('Pharmaceutical Tender Intelligence')
            ->assertSee('Executive Summary')
            ->assertSee('Problem Statement')
            ->assertSee('Tender Program Logic')
            ->assertSee('Graduation Project Relevance')
            ->assertSee('How to Use TenderAI')
            ->assertSee('Why TenderAI Exists')
            ->assertSee('System Pipeline')
            ->assertSee('Conclusion')
            ->assertDontSee('DB_PASSWORD')
            ->assertDontSee('sk_live_');
    }

    public function test_authenticated_user_can_also_access_public_documentation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('public.documentation'))
            ->assertOk()
            ->assertSee('TenderAI Documentation');
    }

    public function test_public_documentation_uses_guest_layout_not_dashboard(): void
    {
        $response = $this->get(route('public.documentation'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('pubdoc-page', $content);
        $this->assertStringContainsString('pubdoc-sidebar', $content);
        $this->assertStringNotContainsString('app-layout', $content);
        $this->assertStringNotContainsString('partials.sidebar', $content);
    }
}
