<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trip extends Model
{
    protected $fillable = ['motorcycle_id', 'distance_km', 'duration_seconds', 'path_json', 'started_at', 'ended_at'];

    protected $casts = [
        'path_json' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }
}
