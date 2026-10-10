<?php

namespace Tests\Unit;

use Tests\TestCase;

class MapProviderTest extends TestCase
{
    public function test_public_token_and_style_are_available_to_the_map(): void
    {
        config(['services.mapbox.access_token' => 'pk.test-token', 'services.mapbox.style' => 'mapbox/streets-v12']);
        $html = view('partials.map-provider')->render();
        $this->assertStringContainsString('pk.test-token', $html);
        $this->assertStringContainsString('streets-v12', $html);
    }

    public function test_secret_token_is_never_exposed_to_the_browser(): void
    {
        config(['services.mapbox.access_token' => 'sk.private-token']);
        $html = view('partials.map-provider')->render();
        $this->assertStringNotContainsString('sk.private-token', $html);
        $this->assertStringContainsString('null', $html);
    }
}
