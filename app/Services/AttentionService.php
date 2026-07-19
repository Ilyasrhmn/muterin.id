<?php

namespace App\Services;

class AttentionService
{
    public function __construct(
        private MaintenanceStatusService $statusService,
        private MaintenancePredictionService $predictionService,
        private FuelStatsService $fuelStatsService,
        private VehicleDocumentService $documentService,
    ) {
    }

    /**
     * ponytail: 14-day "coming due soon" threshold is a tuning knob.
     *
     * @param  iterable<\App\Models\Motorcycle>  $motorcycles  Already loaded with maintenanceItems —
     *   callers should pass a collection they've already fetched instead of a User, to avoid
     *   re-querying motorcycles+items the caller already has in hand.
     */
    public function forUser(iterable $motorcycles): array
    {
        $items = [];

        foreach ($motorcycles as $motor) {
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

            $efficiency = $this->fuelStatsService->efficiencySummary($motor);
            $avg = $efficiency['average'];
            $latest = $efficiency['latest'];
            if ($avg && $latest && $latest < 0.85 * $avg) {
                $items[] = [
                    'severity' => 'yellow',
                    'text' => "Konsumsi BBM {$motor->nickname} turun, cek kondisi mesin",
                    'url' => route('bbm.index'),
                ];
            }

            foreach ($this->documentService->forMotorcycle($motor) as $doc) {
                if ($doc['color'] === 'red') {
                    $overdueText = $doc['days_left'] === 0 ? 'hari ini' : abs($doc['days_left']).' hari lalu';
                    $items[] = [
                        'severity' => 'red',
                        'text' => "Segera bayar {$doc['label']} — {$motor->nickname}, jatuh tempo {$overdueText}",
                        'url' => route('motorcycles.show', $motor),
                    ];
                } elseif ($doc['color'] === 'yellow') {
                    $items[] = [
                        'severity' => 'yellow',
                        'text' => "{$doc['label']} {$motor->nickname} jatuh tempo {$doc['days_left']} hari lagi",
                        'url' => route('motorcycles.show', $motor),
                    ];
                }
            }
        }

        usort($items, fn ($a, $b) => ($a['severity'] === 'red' ? 0 : 1) <=> ($b['severity'] === 'red' ? 0 : 1));

        return $items;
    }
}
