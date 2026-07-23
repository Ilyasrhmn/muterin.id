<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaceList extends Model
{
    public const ICONS = [
        'fa-star', 'fa-flag', 'fa-heart', 'fa-bookmark', 'fa-wrench', 'fa-mug-hot',
        'fa-house', 'fa-camera', 'fa-road', 'fa-mountain', 'fa-utensils', 'fa-gas-pump', 'fa-location-dot',
    ];

    protected $fillable = ['user_id', 'name', 'icon', 'color', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(SavedPlace::class);
    }

    // Buat list bawaan untuk user kalau belum ada. Idempotent.
    // ponytail: daftar & atribut default = tuning-knob.
    public static function ensureDefaultsFor(User $user): void
    {
        $defaults = [
            ['name' => 'Favorit', 'icon' => 'fa-star', 'color' => '#F59E0B'],
            ['name' => 'Mau ke sana', 'icon' => 'fa-flag', 'color' => '#0EA5E9'],
            ['name' => 'Bengkel Langganan', 'icon' => 'fa-wrench', 'color' => '#0F766E'],
        ];
        foreach ($defaults as $d) {
            $user->placeLists()->firstOrCreate(
                ['name' => $d['name'], 'is_default' => true],
                ['icon' => $d['icon'], 'color' => $d['color']],
            );
        }
    }
}
