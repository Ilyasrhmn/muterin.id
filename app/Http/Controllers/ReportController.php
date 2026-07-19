<?php

namespace App\Http\Controllers;

use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Services\FuelStatsService;

class ReportController extends Controller
{
    public function __invoke(FuelStatsService $fuelStats)
    {
        $userId = auth()->id();

        $fuelLogs = \App\Models\FuelLog::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))->get();
        $serviceLogs = \App\Models\MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', $userId))->get();
        $otherExpenses = \App\Models\OtherExpense::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))->get();

        $totalFuelCost = (int) $fuelLogs->sum('total_cost');
        $totalServiceCost = (int) $serviceLogs->sum('cost');
        $totalOtherCost = (int) $otherExpenses->sum('amount');
        $tco = $totalFuelCost + $totalServiceCost + $totalOtherCost;

        $motorcycles = auth()->user()->motorcycles;
        $totalKm = (int) $motorcycles->sum(fn ($m) => max(0, $m->current_odometer_km - $m->initial_odometer_km));
        $costPerKm = $totalKm > 0 ? (int) round($tco / $totalKm) : null;

        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $monthlyFuel = $fuelLogs->groupBy(fn ($l) => $l->filled_at->format('Y-m'));
        $monthlyService = $serviceLogs->groupBy(fn ($l) => $l->serviced_at->format('Y-m'));
        $monthlyOther = $otherExpenses->groupBy(fn ($e) => $e->expense_date->format('Y-m'));

        $trend = $months->map(fn ($m) => [
            'month' => $m,
            'fuel' => (int) $monthlyFuel->get($m, collect())->sum('total_cost'),
            'service' => (int) $monthlyService->get($m, collect())->sum('cost'),
            'other' => (int) $monthlyOther->get($m, collect())->sum('amount'),
        ])->values();

        $efficiencySeries = $motorcycles->mapWithKeys(fn ($m) => [$m->nickname => $fuelStats->consumptionSeries($m)]);

        $efficiencyLabels = $efficiencySeries->flatten(1)->pluck('date')->sort()->unique()->values();

        return view('laporan.index', compact(
            'totalFuelCost', 'totalServiceCost', 'totalOtherCost', 'tco', 'costPerKm', 'trend', 'efficiencySeries', 'efficiencyLabels'
        ));
    }
}
