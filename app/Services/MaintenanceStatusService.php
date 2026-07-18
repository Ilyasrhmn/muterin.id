<?php

namespace App\Services;

use App\Models\MaintenanceItem;

class MaintenanceStatusService
{
    public function percent(int $used, int $interval): float
    {
        if ($interval <= 0) {
            return 0.0; // ponytail: guard bagi nol
        }

        return round($used / $interval * 100, 1);
    }

    public function color(float $percent): string
    {
        if ($percent < 80) {
            return 'green';
        }
        if ($percent <= 100) {
            return 'yellow';
        }

        return 'red';
    }

    public function forItem(MaintenanceItem $item, int $currentOdometer): array
    {
        $used = max(0, $currentOdometer - $item->last_service_odometer_km);
        $percent = $this->percent($used, $item->interval_km);

        return [
            'used' => $used,
            'percent' => $percent,
            'color' => $this->color($percent),
            'remaining' => max(0, $item->interval_km - $used),
        ];
    }
}
