<?php

namespace Tests\Unit;

use App\Exceptions\RouteNotFoundException;
use App\Services\RouteService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteServiceTest extends TestCase
{
    public function test_route_returns_geometry_distance_and_duration(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'features' => [
                    [
                        'geometry' => ['coordinates' => [[106.8, -6.2], [106.81, -6.21], [106.82, -6.22]]],
                        'properties' => ['summary' => ['distance' => 1500.0, 'duration' => 300.0]],
                    ],
                ],
            ], 200),
        ]);

        $service = new RouteService();
        $result = $service->route([[-6.2, 106.8], [-6.22, 106.82]]);

        $this->assertEquals([[-6.2, 106.8], [-6.21, 106.81], [-6.22, 106.82]], $result['geometry']);
        $this->assertEquals(1.5, $result['distance_km']);
        $this->assertEquals(5, $result['duration_minutes']);
    }

    public function test_route_sends_coordinates_to_ors_in_lng_lat_order(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'features' => [
                    [
                        'geometry' => ['coordinates' => [[106.8, -6.2], [106.82, -6.22]]],
                        'properties' => ['summary' => ['distance' => 1000.0, 'duration' => 200.0]],
                    ],
                ],
            ], 200),
        ]);

        $service = new RouteService();
        $service->route([[-6.2, 106.8], [-6.22, 106.82]]);

        Http::assertSent(function ($request) {
            return $request['coordinates'] === [[106.8, -6.2], [106.82, -6.22]];
        });
    }

    public function test_route_throws_when_ors_request_fails(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(['error' => 'no route found'], 404),
        ]);

        $service = new RouteService();

        $this->expectException(RouteNotFoundException::class);
        $service->route([[-6.2, 106.8], [-6.22, 106.82]]);
    }

    public function test_route_throws_when_ors_response_has_no_features(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(['features' => []], 200),
        ]);

        $service = new RouteService();

        $this->expectException(RouteNotFoundException::class);
        $service->route([[-6.2, 106.8], [-6.22, 106.82]]);
    }
}
