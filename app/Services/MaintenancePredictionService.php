<?php

namespace App\Services;

use App\Models\MaintenanceItem;
use App\Models\Motorcycle;
use Illuminate\Support\Carbon;

class MaintenancePredictionService
{
    public function __construct(
        private MaintenanceStatusService $statusService = new MaintenanceStatusService(),
        private OdometerService $odometerService = new OdometerService(),
    ) {
    }

    public function avgKmPerDay(Motorcycle $motorcycle): ?float
    {
        return $this->odometerService->avgKmPerDay($motorcycle);
    }

    public function forItem(MaintenanceItem $item, int $currentOdometer, ?float $avgKmPerDay): array
    {
        if (!$avgKmPerDay || $avgKmPerDay <= 0) {
            return ['days_left' => null, 'predicted_date' => null];
        }

        $status = $this->statusService->forItem($item, $currentOdometer);
        $remainingKm = $status['remaining'];

        $daysLeft = $remainingKm > 0 ? (int) ceil($remainingKm / $avgKmPerDay) : 0;

        return [
            'days_left' => $daysLeft,
            'predicted_date' => Carbon::today()->addDays($daysLeft),
        ];
    }
}
