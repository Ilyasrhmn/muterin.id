<?php

namespace App\Http\Controllers;

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

        return view('dashboard', ['dashboard' => $dashboard]);
    }
}
