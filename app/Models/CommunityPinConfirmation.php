<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPinConfirmation extends Model
{
    protected $fillable = ['community_pin_id', 'user_id', 'still_there'];

    protected $casts = ['still_there' => 'boolean'];

    public function pin(): BelongsTo
    {
        return $this->belongsTo(CommunityPin::class, 'community_pin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
