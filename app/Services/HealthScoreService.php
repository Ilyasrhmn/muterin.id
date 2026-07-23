<?php

namespace App\Services;

use App\Models\Motorcycle;

class HealthScoreService
{
    public function __construct(
        private MaintenanceStatusService $statusService,
        private FuelStatsService $fuelStatsService,
    ) {
    }

    /**
     * Composite 0-100 score from maintenance status + fuel efficiency trend.
     * ponytail: penalty weights (15/5/10) and the 0.85 efficiency-drop
     * threshold are tuning knobs  adjust if the score feels miscalibrated
     * against real usage.
     */
    public function forMotorcycle(Motorcycle $motorcycle): array
    {
        $score = 100;

        foreach ($motorcycle->maintenanceItems as $item) {
            $status = $this->statusService->forItem($item, $motorcycle->current_odometer_km);

            if ($status['percent'] > 100) {
                $score -= 15;
            } elseif ($status['percent'] >= 80) {
                $score -= 5;
            }
        }

        $efficiency = $this->fuelStatsService->efficiencySummary($motorcycle);
        $avg = $efficiency['average'];
        $latest = $efficiency['latest'];
        if ($avg && $latest && $latest < 0.85 * $avg) {
            $score -= 10;
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'label' => $score >= 80 ? 'Sehat' : ($score >= 60 ? 'Perlu Perhatian' : 'Butuh Servis'),
            'color' => $score >= 80 ? 'green' : ($score >= 60 ? 'yellow' : 'red'),
        ];
    }
}
