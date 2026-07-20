<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_has_section_links_and_mobile_menu(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('#cara-kerja', false);
        $response->assertSee('#faq', false);
        $response->assertSee('Buka menu', false);
    }

    public function test_footer_has_nav_columns_and_wordmark(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Navigasi', false);
        $response->assertSee('AMICTA', false);
    }

    public function test_hero_describes_multi_source_odometer_not_gps_only(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('km yang benar-benar akurat');
        $response->assertDontSee('Amicta merekam perjalananmu lewat GPS');
    }

    public function test_problem_and_stats_reflect_real_capabilities(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('nilai jual lebih tinggi');
        $response->assertSee('Sumber pencatatan km');
        $response->assertSee('Modul lengkap dalam satu aplikasi');
        $response->assertDontSee('Berbasis km asli via GPS');
    }

    public function test_features_section_has_four_expandable_pillars(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Pantau Kondisi Motor');
        $response->assertSee('Jangan Ada yang Kelewat');
        $response->assertSee('Kontrol Biaya Penuh');
        $response->assertSee('Riding &amp; Peta Pribadi', false);
    }
}
