<?php

namespace Tests\Unit;

use App\Exceptions\GeocodingException;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    public function test_search_parses_results_and_converts_coordinate_order(): void
    {
        Http::fake([
            'api.openrouteservice.org/geocode/search*' => Http::response([
                'features' => [
                    ['properties' => ['label' => 'Jalan Bintaro, Jakarta'], 'geometry' => ['coordinates' => [106.75, -6.27]]],
                    ['properties' => ['label' => 'Bintaro Plaza'], 'geometry' => ['coordinates' => [106.76, -6.28]]],
                ],
            ], 200),
        ]);

        $result = (new GeocodingService())->search('bintaro');

        $this->assertCount(2, $result);
        $this->assertEquals(['label' => 'Jalan Bintaro, Jakarta', 'lat' => -6.27, 'lng' => 106.75], $result[0]);
    }

    public function test_search_returns_empty_array_when_no_features(): void
    {
        Http::fake(['api.openrouteservice.org/geocode/search*' => Http::response(['features' => []], 200)]);

        $this->assertSame([], (new GeocodingService())->search('zzznowhere'));
    }

    public function test_search_throws_on_http_failure(): void
    {
        Http::fake(['api.openrouteservice.org/geocode/search*' => Http::response(['error' => 'quota'], 429)]);

        $this->expectException(GeocodingException::class);
        (new GeocodingService())->search('bintaro');
    }

    public function test_reverse_returns_nearest_label(): void
    {
        Http::fake([
            'api.openrouteservice.org/geocode/reverse*' => Http::response([
                'features' => [
                    ['properties' => ['label' => 'Jalan Merpati, Bintaro'], 'geometry' => ['coordinates' => [106.75, -6.27]]],
                ],
            ], 200),
        ]);

        $result = (new GeocodingService())->reverse(-6.27, 106.75);

        $this->assertEquals('Jalan Merpati, Bintaro', $result['label']);
        $this->assertEquals(-6.27, $result['lat']);
        $this->assertEquals(106.75, $result['lng']);
    }

    public function test_reverse_falls_back_when_no_feature(): void
    {
        Http::fake(['api.openrouteservice.org/geocode/reverse*' => Http::response(['features' => []], 200)]);

        $result = (new GeocodingService())->reverse(-6.5, 106.5);

        $this->assertEquals('Lokasi tanpa nama', $result['label']);
        $this->assertEquals(-6.5, $result['lat']);
        $this->assertEquals(106.5, $result['lng']);
    }

    public function test_reverse_throws_on_http_failure(): void
    {
        Http::fake(['api.openrouteservice.org/geocode/reverse*' => Http::response(['error' => 'down'], 500)]);

        $this->expectException(GeocodingException::class);
        (new GeocodingService())->reverse(-6.27, 106.75);
    }
}
