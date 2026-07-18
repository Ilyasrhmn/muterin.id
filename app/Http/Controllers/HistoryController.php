<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use Barryvdh\DomPDF\Facade\Pdf;

class HistoryController extends Controller
{
    public function __invoke()
    {
        $logs = $this->serviceLogs();

        $totalCost = $logs->sum('cost');
        $servicedCount = $logs->count();
        $avgCost = $servicedCount ? (int) round($totalCost / $servicedCount) : 0;
        $thisMonthCost = $logs->where('serviced_at', '>=', now()->startOfMonth())->sum('cost');

        $breakdown = $logs->groupBy(fn ($log) => $log->item->name)
            ->map(fn ($group) => $group->sum('cost'))
            ->sortDesc();

        return view('history.index', compact('logs', 'totalCost', 'servicedCount', 'avgCost', 'thisMonthCost', 'breakdown'));
    }

    public function exportPdf()
    {
        $logs = $this->serviceLogs();
        $totalCost = $logs->sum('cost');

        $pdf = Pdf::loadView('history.export-pdf', compact('logs', 'totalCost'));

        return $pdf->download('riwayat-servis.pdf');
    }

    private function serviceLogs()
    {
        $userId = auth()->id();

        return MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', $userId))
            ->with('item.motorcycle')->latest('serviced_at')->get();
    }
}
