<?php

namespace App\Services;

use App\Models\CommunityPin;
use App\Models\CommunityPinConfirmation;
use App\Models\CommunityPinFavorite;
use App\Models\User;
use Illuminate\Support\Collection;

class CommunityPinService
{
    public function visiblePins(): Collection
    {
        return CommunityPin::visible()->with('user')->latest()->get();
    }

    // Toggle: sudah difavoritkan -> hapus, belum -> buat. Return status baru.
    public function toggleFavorite(CommunityPin $pin, User $user): bool
    {
        $favorite = CommunityPinFavorite::where('community_pin_id', $pin->id)
            ->where('user_id', $user->id)->first();

        if ($favorite) {
            $favorite->delete();

            return false;
        }

        CommunityPinFavorite::create(['community_pin_id' => $pin->id, 'user_id' => $user->id]);

        return true;
    }

    // Pin id yang di-like (still_there=true) oleh user ini. Dipanggil sekali
    // per-request, dipakai present() lewat in_array() -- hindari N+1.
    public function likedPinIds(User $user): array
    {
        return CommunityPinConfirmation::where('user_id', $user->id)
            ->where('still_there', true)->pluck('community_pin_id')->all();
    }

    // Pin id yang difavoritkan user ini. Sama pola dengan likedPinIds().
    public function favoritedPinIds(User $user): array
    {
        return CommunityPinFavorite::where('user_id', $user->id)->pluck('community_pin_id')->all();
    }

    // Return: [still_there_count, no_longer_count]. confirm_count (net skor,
    // bisa negatif) tetap disimpan buat scopeVisible -- ini cuma dipakai
    // internal buat auto-hide pin lama yang mayoritas dibantah, BUKAN buat
    // ditampilkan ke user (angka net signed nggak masuk akal ditampilkan
    // sebagai "dikonfirmasi N orang").
    public function confirm(CommunityPin $pin, User $user, bool $stillThere): array
    {
        CommunityPinConfirmation::updateOrCreate(
            ['community_pin_id' => $pin->id, 'user_id' => $user->id],
            ['still_there' => $stillThere],
        );

        $true = $pin->confirmations()->where('still_there', true)->count();
        $false = $pin->confirmations()->where('still_there', false)->count();
        $pin->update(['confirm_count' => $true - $false, 'still_count' => $true, 'gone_count' => $false]);

        return [$true, $false];
    }

    // Pin yang jaraknya <= threshold meter dari vertex terdekat pada polyline rute.
    // ponytail: jarak ke vertex terdekat (bukan titik-ke-segmen)  geometry ORS rapat,
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
