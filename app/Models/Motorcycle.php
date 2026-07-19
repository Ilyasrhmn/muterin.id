<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Motorcycle extends Model
{
    protected $fillable = [
        'user_id', 'nickname', 'plat_nomor', 'brand', 'model', 'year',
        'initial_odometer_km', 'current_odometer_km', 'is_active',
        'stnk_due_date', 'plat_due_date', 'insurance_due_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'stnk_due_date' => 'date',
        'plat_due_date' => 'date',
        'insurance_due_date' => 'date',
    ];

    public const DEFAULT_ITEMS = [
        ['name' => 'Oli Mesin', 'interval_km' => 2500],
        ['name' => 'Ban', 'interval_km' => 12000],
        ['name' => 'Aki', 'interval_km' => 15000],
        ['name' => 'Servis Rutin', 'interval_km' => 4000],
    ];

    protected static function booted(): void
    {
        static::created(function (Motorcycle $motor) {
            $startOdometer = $motor->initial_odometer_km ?? 0;
            foreach (self::DEFAULT_ITEMS as $item) {
                $motor->maintenanceItems()->create([
                    'name' => $item['name'],
                    'interval_km' => $item['interval_km'],
                    'last_service_odometer_km' => $startOdometer,
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maintenanceItems(): HasMany
    {
        return $this->hasMany(MaintenanceItem::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }

    public function odometerReadings(): HasMany
    {
        return $this->hasMany(OdometerReading::class);
    }

    public function otherExpenses(): HasMany
    {
        return $this->hasMany(OtherExpense::class);
    }
}
