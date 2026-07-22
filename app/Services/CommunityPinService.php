<?php

namespace App\Services;

use App\Models\CommunityPin;
use App\Models\CommunityPinConfirmation;
use App\Models\User;
use Illuminate\Support\Collection;

class CommunityPinService
{
    public function visiblePins(): Collection
    {
        return CommunityPin::visible()->with('user')->latest()->get();
    }

    public function confirm(CommunityPin $pin, User $user, bool $stillThere): int
    {
        CommunityPinConfirmation::updateOrCreate(
            ['community_pin_id' => $pin->id, 'user_id' => $user->id],
            ['still_there' => $stillThere],
        );

        $true = $pin->confirmations()->where('still_there', true)->count();
        $false = $pin->confirmations()->where('still_there', false)->count();
        $count = $true - $false;
        $pin->update(['confirm_count' => $count]);

        return $count;
    }

    // Pin yang jaraknya <= threshold meter dari vertex terdekat pada polyline rute.
    // ponytail: jarak ke vertex terdekat (bukan titik-ke-segmen) — geometry ORS rapat,
    // aproksimasi ini cukup; naikkan ke titik-ke-segmen kalau meleset.
    public function nearRoute(array $geometry, float $thresholdMeters = 300): Collection
    {
        if (count($geometry) < 1) {
            return collect();
        }

        return $this->visiblePins()->filter(function (CommunityPin $pin) use ($geometry, $thresholdMeters) {
            foreach ($geometry as [$lat, $lng]) {
                if ($this->haversine($pin->lat, $pin->lng, (float) $lat, (float) $lng) <= $thresholdMeters) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000; // meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
