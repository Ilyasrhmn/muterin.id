<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    protected $fillable = [
        'motorcycle_id', 'filled_at', 'odometer_km', 'liters', 'total_cost', 'is_full_tank', 'note',
    ];

    protected $casts = [
        'filled_at' => 'date',
        'is_full_tank' => 'boolean',
        'liters' => 'decimal:2',
    ];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }
}
