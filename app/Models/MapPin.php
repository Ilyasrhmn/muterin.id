<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapPin extends Model
{
    protected $fillable = ['user_id', 'category', 'lat', 'lng', 'title', 'note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
