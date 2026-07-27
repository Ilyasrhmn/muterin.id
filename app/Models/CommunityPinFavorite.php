<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPinFavorite extends Model
{
    protected $fillable = ['community_pin_id', 'user_id'];

    public function pin(): BelongsTo
    {
        return $this->belongsTo(CommunityPin::class, 'community_pin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
