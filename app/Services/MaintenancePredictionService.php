<?php

namespace App\Services;

use App\Models\MaintenanceItem;
use App\Models\Motorcycle;
use Illuminate\Support\Carbon;

class MaintenancePredictionService
{
    public function __construct(
        private MaintenanceStatusService $statusService = new MaintenanceStatusService(),
    ) {
    }

    /**
     * Average km ridden per day, from trips in the last 30 days.
     * Falls back to lifetime average (odometer growth / days since created)
     * when there is no recent trip data.
     *
     * ponytail: 30-day window is a tuning knob, not a fixed law — revisit if
     * predictions feel stale for infrequent riders.
     */
    public function avgKmPerDay(Motorcycle $motorcycle): ?float
    {
        $recentKm = (float) $motorcycle->trips()
            ->where('ended_at', '>=', now()->subDays(30))
            ->sum('distance_km');

        if ($recentKm > 0) {
            return round($recentKm / 30, 2);
        }

        $daysSinceCreated = max(1, $motorcycle->created_at->diffInDays(now()));
        $totalKm = $motorcycle->current_odometer_km - $motorcycle->initial_odometer_km;

        if ($totalKm <= 0) {
            return null;
        }

        return round($totalKm / $daysSinceCreated, 2);
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
