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
}
