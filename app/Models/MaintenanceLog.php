<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    protected $fillable = ['maintenance_item_id', 'serviced_at_odometer_km', 'cost', 'serviced_at', 'note'];

    protected $casts = ['serviced_at' => 'date'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MaintenanceItem::class, 'maintenance_item_id');
    }
}
