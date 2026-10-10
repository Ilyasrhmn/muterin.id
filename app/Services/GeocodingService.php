<?php

namespace App\Services;

use App\Exceptions\GeocodingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    private const ERROR_MESSAGE = 'Gagal mencari lokasi. Coba lagi sebentar.';

    public function search(string $query, ?float $focusLat = null, ?float $focusLng = null): array
    {
        $params = [
            'q' => $query,
            'limit' => 5,
            'access_token' => config('services.mapbox.access_token'),
            'permanent' => 'true',
            'language' => 'id',
        ];

        if ($focusLat !== null && $focusLng !== null) {
            $params['proximity'] = $focusLng.','.$focusLat;
        }

        try {
            $response = Http::timeout(8)->get('https://api.mapbox.com/search/geocode/v6/forward', $params);
        } catch (ConnectionException) {
            throw new GeocodingException(self::ERROR_MESSAGE);
        }

        if ($response->failed()) {
            throw new GeocodingException(self::ERROR_MESSAGE);
        }

        return collect($response->json('features', []))
            ->map(fn ($feature) => [
                'label' => $feature['properties']['full_address'] ?? 'Lokasi tanpa nama',
                'lat' => $feature['geometry']['coordinates'][1],
                'lng' => $feature['geometry']['coordinates'][0],
            ])
            ->values()
            ->all();
    }

    public function reverse(float $lat, float $lng): array
    {
        try {
            $response = Http::timeout(8)->get('https://api.mapbox.com/search/geocode/v6/reverse', [
                'latitude' => $lat,
                'longitude' => $lng,
                'limit' => 1,
                'access_token' => config('services.mapbox.access_token'),
                'permanent' => 'true',
                'language' => 'id',
            ]);
        } catch (ConnectionException) {
            throw new GeocodingException(self::ERROR_MESSAGE);
        }

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
            'label' => $feature['properties']['full_address'] ?? 'Lokasi tanpa nama',
            'lat' => $lat,
            'lng' => $lng,
        ];
    }
}
