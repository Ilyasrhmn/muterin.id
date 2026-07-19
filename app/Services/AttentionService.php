<?php

namespace App\Services;

use App\Models\User;

class AttentionService
{
    public function __construct(
        private MaintenanceStatusService $statusService,
        private MaintenancePredictionService $predictionService,
        private FuelStatsService $fuelStatsService,
    ) {
    }

    /**
     * ponytail: 14-day "coming due soon" threshold is a tuning knob.
     */
    public function forUser(User $user): array
    {
        $items = [];

        foreach ($user->motorcycles as $motor) {
            $avgKmPerDay = $this->predictionService->avgKmPerDay($motor);

            foreach ($motor->maintenanceItems as $item) {
                $status = $this->statusService->forItem($item, $motor->current_odometer_km);

                if ($status['percent'] > 100) {
                    $items[] = [
                        'severity' => 'red',
                        'text' => "Segera servis {$item->name} — {$motor->nickname}",
                        'url' => route('motorcycles.show', $motor),
                    ];
                    continue;
                }

                $prediction = $this->predictionService->forItem($item, $motor->current_odometer_km, $avgKmPerDay);
                if ($prediction['days_left'] !== null && $prediction['days_left'] <= 14) {
                    $items[] = [
                        'severity' => 'yellow',
                        'text' => "{$item->name} {$motor->nickname} diperkirakan ~{$prediction['days_left']} hari lagi",
                        'url' => route('motorcycles.show', $motor),
                    ];
                }
            }

            $avg = $this->fuelStatsService->averageKmPerLiter($motor);
            $latest = $this->fuelStatsService->latestKmPerLiter($motor);
            if ($avg && $latest && $latest < 0.85 * $avg) {
                $items[] = [
                    'severity' => 'yellow',
                    'text' => "Konsumsi BBM {$motor->nickname} turun, cek kondisi mesin",
                    'url' => route('bbm.index'),
                ];
            }
        }

        usort($items, fn ($a, $b) => ($a['severity'] === 'red' ? 0 : 1) <=> ($b['severity'] === 'red' ? 0 : 1));

        return $items;
    }
}
