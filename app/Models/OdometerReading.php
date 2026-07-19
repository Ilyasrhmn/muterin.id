<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdometerReading extends Model
{
    protected $fillable = ['motorcycle_id', 'reading_km', 'recorded_at', 'source', 'note'];

    protected $casts = ['recorded_at' => 'date'];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }
}
