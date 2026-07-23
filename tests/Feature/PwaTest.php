<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_has_manifest_link_and_theme_color(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/manifest.json">', false);
        $response->assertSee('<meta name="theme-color" content="#0F766E">', false);
    }

    public function test_login_page_has_manifest_link(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/manifest.json">', false);
    }

    public function test_manifest_is_valid_json_with_required_fields(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $this->assertSame('Muterin', $manifest['name']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_offline_page_exists(): void
    {
        $this->assertStringContainsString('Kamu sedang offline', file_get_contents(public_path('offline.html')));
    }

    public function test_service_worker_exists(): void
    {
        $this->assertFileExists(public_path('sw.js'));
    }

    public function test_dashboard_layout_includes_pwa_registration_script(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('js/pwa.js', false);
    }
}
