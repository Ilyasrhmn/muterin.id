<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceItem extends Model
{
    protected $fillable = ['motorcycle_id', 'name', 'interval_km', 'last_service_odometer_km'];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }
}
