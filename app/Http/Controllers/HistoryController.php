<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;

class HistoryController extends Controller
{
    public function __invoke()
    {
        [$logs, $totalCost] = $this->serviceLogs();
        $trips = $this->trips();

        return view('history.index', compact('trips', 'logs', 'totalCost'));
    }

    public function exportPdf()
    {
        [$logs, $totalCost] = $this->serviceLogs();

        $pdf = Pdf::loadView('history.export-pdf', compact('logs', 'totalCost'));

        return $pdf->download('riwayat-servis.pdf');
    }

    private function trips()
    {
        $userId = auth()->id();

        return Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))
            ->with('motorcycle')->latest('ended_at')->get();
    }

    private function serviceLogs(): array
    {
        $userId = auth()->id();

        $logs = MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', $userId))
            ->with('item.motorcycle')->latest('serviced_at')->get();

        return [$logs, $logs->sum('cost')];
    }
}
