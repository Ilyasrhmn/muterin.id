<?php

namespace App\Services;

use App\Models\Motorcycle;

class FuelStatsService
{
    public function consumptionSeries(Motorcycle $motorcycle): array
    {
        $logs = $motorcycle->fuelLogs()->orderBy('filled_at')->orderBy('id')->get();

        $series = [];
        $prevFull = null;

        foreach ($logs as $log) {
            if ($prevFull !== null && $log->is_full_tank) {
                $distance = $log->odometer_km - $prevFull->odometer_km;
                $liters = (float) $log->liters;
                if ($distance > 0 && $liters > 0) {
                    $series[] = [
                        'date' => $log->filled_at->toDateString(),
                        'km_per_liter' => round($distance / $liters, 1),
                    ];
                }
            }
            if ($log->is_full_tank) {
                $prevFull = $log;
            }
        }

        return $series;
    }

    public function averageKmPerLiter(Motorcycle $motorcycle): ?float
    {
        $series = $this->consumptionSeries($motorcycle);
        if (empty($series)) {
            return null;
        }

        return round(array_sum(array_column($series, 'km_per_liter')) / count($series), 1);
    }

    public function latestKmPerLiter(Motorcycle $motorcycle): ?float
    {
        $series = $this->consumptionSeries($motorcycle);

        return empty($series) ? null : end($series)['km_per_liter'];
    }

    public function costPerKm(Motorcycle $motorcycle): ?float
    {
        $logs = $motorcycle->fuelLogs()->orderBy('odometer_km')->get();
        if ($logs->count() < 2) {
            return null;
        }

        $distance = $logs->last()->odometer_km - $logs->first()->odometer_km;
        if ($distance <= 0) {
            return null;
        }

        return round($logs->sum('total_cost') / $distance, 0);
    }
}
