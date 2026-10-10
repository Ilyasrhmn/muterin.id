<?php

namespace App\Services;

use App\Exceptions\RouteNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class RouteService
{
    private const ERROR_MESSAGE = 'Gagal menghitung rute jalan. Coba lagi sebentar.';

    public function route(array $waypoints): array
    {
        $coordinates = implode(';', array_map(fn ($point) => $point[1].','.$point[0], $waypoints));

        try {
            $response = Http::timeout(8)->get('https://api.mapbox.com/directions/v5/mapbox/driving/'.$coordinates, [
                'access_token' => config('services.mapbox.access_token'),
                'geometries' => 'geojson',
                'overview' => 'full',
                'exclude' => 'motorway',
            ]);
        } catch (ConnectionException) {
            throw new RouteNotFoundException(self::ERROR_MESSAGE);
        }

        if ($response->failed()) {
            throw new RouteNotFoundException(self::ERROR_MESSAGE);
        }

        $feature = $response->json('routes.0');

        if (!$feature) {
            throw new RouteNotFoundException(self::ERROR_MESSAGE);
        }

        $geometry = array_map(fn ($coord) => [$coord[1], $coord[0]], $feature['geometry']['coordinates']);
        $summary = $feature;

        return [
            'geometry' => $geometry,
            'distance_km' => round($summary['distance'] / 1000, 2),
            'duration_minutes' => (int) round($summary['duration'] / 60),
        ];
    }
}
