<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use App\Models\Trip;

class HistoryController extends Controller
{
    public function __invoke()
    {
        $userId = auth()->id();

        $trips = Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))
            ->with('motorcycle')->latest('ended_at')->get();

        $logs = MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', $userId))
            ->with('item.motorcycle')->latest('serviced_at')->get();

        $totalCost = $logs->sum('cost');

        return view('history.index', compact('trips', 'logs', 'totalCost'));
    }
}
