<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use App\Services\MaintenanceStatusService;

class DashboardController extends Controller
{
    public function __invoke(MaintenanceStatusService $status)
    {
        $motorcycles = auth()->user()->motorcycles()->with('maintenanceItems')->get();

        $dashboard = $motorcycles->map(function ($motor) use ($status) {
            return [
                'motor' => $motor,
                'items' => $motor->maintenanceItems->map(fn ($item) => [
                    'item' => $item,
                    'status' => $status->forItem($item, $motor->current_odometer_km),
                ]),
            ];
        });

        $kpi = [
            'motor_count' => $motorcycles->count(),
            'total_km' => $motorcycles->sum('current_odometer_km'),
            'attention' => $dashboard->sum(fn ($row) => $row['items']->filter(fn ($i) => $i['status']['color'] !== 'green')->count()),
            'total_cost' => MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', auth()->id()))->sum('cost'),
        ];

        return view('dashboard', ['dashboard' => $dashboard, 'kpi' => $kpi]);
    }
}
