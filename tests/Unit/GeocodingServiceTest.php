<?php

namespace Tests\Unit;

use App\Exceptions\GeocodingException;
use App\Services\GeocodingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    public function test_search_uses_mapbox_proximity_and_permanent_storage(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::response(['features' => []])]);
        (new GeocodingService())->search('Yogyakarta', -7.8, 110.3);
        Http::assertSent(fn ($request) => $request['proximity'] === '110.3,-7.8'
            && $request['permanent'] === 'true'
            && $request['q'] === 'Yogyakarta');
    }

    public function test_reverse_uses_mapbox_coordinate_parameters(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::response(['features' => []])]);
        (new GeocodingService())->reverse(-7.8, 110.3);
        Http::assertSent(fn ($request) => $request['latitude'] == -7.8
            && $request['longitude'] == 110.3 && $request['permanent'] === 'true');
    }

    public function test_search_parses_results_and_converts_coordinate_order(): void
    {
        Http::fake([
            'api.mapbox.com/search/geocode/v6/forward*' => Http::response([
                'features' => [
                    ['properties' => ['full_address' => 'Jalan Bintaro, Jakarta'], 'geometry' => ['coordinates' => [106.75, -6.27]]],
                    ['properties' => ['full_address' => 'Bintaro Plaza'], 'geometry' => ['coordinates' => [106.76, -6.28]]],
                ],
            ], 200),
        ]);

        $result = (new GeocodingService())->search('bintaro');

        $this->assertCount(2, $result);
        $this->assertEquals(['label' => 'Jalan Bintaro, Jakarta', 'lat' => -6.27, 'lng' => 106.75], $result[0]);
    }

    public function test_search_returns_empty_array_when_no_features(): void
    {
        Http::fake(['api.mapbox.com/search/geocode/v6/forward*' => Http::response(['features' => []], 200)]);

        $this->assertSame([], (new GeocodingService())->search('zzznowhere'));
    }

    public function test_search_throws_on_http_failure(): void
    {
        Http::fake(['api.mapbox.com/search/geocode/v6/forward*' => Http::response(['error' => 'quota'], 429)]);

        $this->expectException(GeocodingException::class);
        (new GeocodingService())->search('bintaro');
    }

    public function test_search_throws_geocoding_exception_on_connection_timeout(): void
    {
        Http::fake([
            'api.mapbox.com/search/geocode/v6/forward*' => fn () => throw new ConnectionException('cURL error 28: Connection timed out'),
        ]);

        $this->expectException(GeocodingException::class);
        (new GeocodingService())->search('bintaro');
    }

    public function test_reverse_returns_nearest_label(): void
    {
        Http::fake([
            'api.mapbox.com/search/geocode/v6/reverse*' => Http::response([
                'features' => [
                    ['properties' => ['full_address' => 'Jalan Merpati, Bintaro'], 'geometry' => ['coordinates' => [106.75, -6.27]]],
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
        Http::fake(['api.mapbox.com/search/geocode/v6/reverse*' => Http::response(['features' => []], 200)]);

        $result = (new GeocodingService())->reverse(-6.5, 106.5);

        $this->assertEquals('Lokasi tanpa nama', $result['label']);
        $this->assertEquals(-6.5, $result['lat']);
        $this->assertEquals(106.5, $result['lng']);
    }

    public function test_reverse_throws_on_http_failure(): void
    {
        Http::fake(['api.mapbox.com/search/geocode/v6/reverse*' => Http::response(['error' => 'down'], 500)]);

        $this->expectException(GeocodingException::class);
        (new GeocodingService())->reverse(-6.27, 106.75);
    }

    public function test_reverse_throws_geocoding_exception_on_connection_timeout(): void
    {
        Http::fake([
            'api.mapbox.com/search/geocode/v6/reverse*' => fn () => throw new ConnectionException('cURL error 28: Connection timed out'),
        ]);

        $this->expectException(GeocodingException::class);
        (new GeocodingService())->reverse(-6.27, 106.75);
    }
}
