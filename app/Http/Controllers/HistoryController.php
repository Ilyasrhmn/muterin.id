<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use Barryvdh\DomPDF\Facade\Pdf;

class HistoryController extends Controller
{
    public function __invoke()
    {
        $logs = $this->serviceLogs();
        $otherExpenses = $this->otherExpenses();

        $serviceCost = $logs->sum('cost');
        $otherCost = $otherExpenses->sum('amount');
        $totalCost = $serviceCost + $otherCost;

        $servicedCount = $logs->count();
        $avgCost = $servicedCount ? (int) round($serviceCost / $servicedCount) : 0;
        $thisMonthCost = $logs->where('serviced_at', '>=', now()->startOfMonth())->sum('cost')
            + $otherExpenses->where('expense_date', '>=', now()->startOfMonth())->sum('amount');

        $breakdown = $logs->groupBy(fn ($log) => $log->item->name)
            ->map(fn ($group) => $group->sum('cost'))
            ->mergeRecursive(
                $otherExpenses->groupBy(fn ($e) => \App\Models\OtherExpense::CATEGORY_LABELS[$e->category])
                    ->map(fn ($group) => $group->sum('amount'))
            )
            ->map(fn ($value) => is_array($value) ? array_sum($value) : $value)
            ->sortDesc();

        return view('history.index', compact('logs', 'otherExpenses', 'totalCost', 'servicedCount', 'avgCost', 'thisMonthCost', 'breakdown'));
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

    private function otherExpenses()
    {
        $userId = auth()->id();

        return \App\Models\OtherExpense::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))
            ->with('motorcycle')->latest('expense_date')->get();
    }
}
