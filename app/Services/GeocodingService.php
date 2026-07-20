<?php

namespace App\Services;

use App\Exceptions\GeocodingException;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    private const ERROR_MESSAGE = 'Gagal mencari lokasi. Coba lagi sebentar.';

    public function search(string $query, ?float $focusLat = null, ?float $focusLng = null): array
    {
        $params = [
            'text' => $query,
            'size' => 5,
        ];

        if ($focusLat !== null && $focusLng !== null) {
            $params['focus.point.lat'] = $focusLat;
            $params['focus.point.lon'] = $focusLng;
        }

        $response = Http::withHeaders([
            'Authorization' => config('services.ors.key'),
        ])->timeout(8)->get('https://api.openrouteservice.org/geocode/search', $params);

        if ($response->failed()) {
            throw new GeocodingException(self::ERROR_MESSAGE);
        }

        return collect($response->json('features', []))
            ->map(fn ($feature) => [
                'label' => $feature['properties']['label'] ?? 'Lokasi tanpa nama',
                'lat' => $feature['geometry']['coordinates'][1],
                'lng' => $feature['geometry']['coordinates'][0],
            ])
            ->values()
            ->all();
    }

    public function reverse(float $lat, float $lng): array
    {
        $response = Http::withHeaders([
            'Authorization' => config('services.ors.key'),
        ])->timeout(8)->get('https://api.openrouteservice.org/geocode/reverse', [
            'point.lat' => $lat,
            'point.lon' => $lng,
            'size' => 1,
        ]);

        if ($response->failed()) {
            throw new GeocodingException(self::ERROR_MESSAGE);
        }

        $feature = $response->json('features.0');

        if (! $feature) {
            return [
                'label' => 'Lokasi tanpa nama',
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return [
            'label' => $feature['properties']['label'] ?? 'Lokasi tanpa nama',
            'lat' => $lat,
            'lng' => $lng,
        ];
    }
}
