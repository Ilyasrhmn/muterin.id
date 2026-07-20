<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutePlan extends Model
{
    protected $fillable = ['user_id', 'name', 'points_json', 'route_geometry_json', 'distance_km', 'duration_minutes', 'start_label', 'end_label'];

    protected $casts = ['points_json' => 'array', 'route_geometry_json' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
