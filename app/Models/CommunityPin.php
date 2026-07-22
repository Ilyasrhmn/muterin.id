<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPin extends Model
{
    protected $fillable = [
        'user_id', 'category', 'lat', 'lng', 'title', 'description',
        'photo_path', 'time_context', 'is_anonymous', 'confirm_count',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'confirm_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(CommunityPinConfirmation::class);
    }

    // Sembunyikan hanya bila tua (>30 hari) DAN mayoritas bilang "udah nggak".
    // ponytail: ambang 30 hari adalah tuning-knob.
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('created_at', '>=', now()->subDays(30))
                ->orWhere('confirm_count', '>=', 0);
        });
    }
}
