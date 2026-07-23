<?php

namespace App\Services;

use App\Models\Motorcycle;
use App\Models\OdometerReading;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OdometerService
{
    public function record(Motorcycle $motorcycle, int $km, Carbon $date, string $source, ?string $note = null): OdometerReading
    {
        if ($km < $motorcycle->current_odometer_km) {
            throw ValidationException::withMessages([
                'odometer_km' => "Odometer tidak boleh lebih kecil dari {$motorcycle->current_odometer_km} km (bacaan terakhir).",
            ]);
        }

        $reading = $motorcycle->odometerReadings()->create([
            'reading_km' => $km,
            'recorded_at' => $date,
            'source' => $source,
            'note' => $note,
        ]);

        if ($km > $motorcycle->current_odometer_km) {
            $motorcycle->update(['current_odometer_km' => $km]);
        }

        return $reading;
    }

    /**
     * ponytail: 30-day fixed window + lifetime fallback mirrors
     * MaintenancePredictionService's original trip-based logic  tuning
     * knob, not a fixed law.
     */
    public function avgKmPerDay(Motorcycle $motorcycle): ?float
    {
        $readings = $motorcycle->odometerReadings()
            ->where('recorded_at', '>=', now()->subDays(30))
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        if ($readings->count() >= 2) {
            $delta = $readings->last()->reading_km - $readings->first()->reading_km;
            if ($delta > 0) {
                return round($delta / 30, 2);
            }
        }

        $daysSinceCreated = max(1, $motorcycle->created_at->diffInDays(now()));
        $totalKm = $motorcycle->current_odometer_km - $motorcycle->initial_odometer_km;

        if ($totalKm <= 0) {
            return null;
        }

        return round($totalKm / $daysSinceCreated, 2);
    }
}
