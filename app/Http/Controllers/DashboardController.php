<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use App\Services\AttentionService;
use App\Services\HealthScoreService;
use App\Services\MaintenancePredictionService;
use App\Services\MaintenanceStatusService;

class DashboardController extends Controller
{
    public function __invoke(
        MaintenanceStatusService $status,
        MaintenancePredictionService $prediction,
        HealthScoreService $healthScore,
        AttentionService $attention
    ) {
        $motorcycles = auth()->user()->motorcycles()->with('maintenanceItems')->get();

        $dashboard = $motorcycles->map(function ($motor) use ($status, $prediction, $healthScore) {
            $avgKmPerDay = $prediction->avgKmPerDay($motor);

            return [
                'motor' => $motor,
                'health' => $healthScore->forMotorcycle($motor),
                'items' => $motor->maintenanceItems->map(fn ($item) => [
                    'item' => $item,
                    'status' => $status->forItem($item, $motor->current_odometer_km),
                    'prediction' => $prediction->forItem($item, $motor->current_odometer_km, $avgKmPerDay),
                ]),
            ];
        });

        $kpi = [
            'motor_count' => $motorcycles->count(),
            'total_km' => $motorcycles->sum('current_odometer_km'),
            'attention' => $dashboard->sum(fn ($row) => $row['items']->filter(fn ($i) => $i['status']['color'] !== 'green')->count()),
            'total_cost' => MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', auth()->id()))->sum('cost'),
        ];

        $attentionItems = $attention->forUser($motorcycles);

        return view('dashboard', ['dashboard' => $dashboard, 'kpi' => $kpi, 'attentionItems' => $attentionItems]);
    }
}
