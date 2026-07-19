<?php

namespace App\Http\Controllers;

use App\Models\FuelLog;
use App\Models\Motorcycle;
use App\Services\FuelStatsService;
use Illuminate\Http\Request;

class FuelController extends Controller
{
    public function index(FuelStatsService $stats)
    {
        $motorcycles = auth()->user()->motorcycles()->get();

        $logs = FuelLog::whereHas('motorcycle', fn ($q) => $q->where('user_id', auth()->id()))
            ->with('motorcycle')
            ->orderByDesc('filled_at')
            ->orderByDesc('id')
            ->get();

        $motorStats = $motorcycles->map(function ($m) use ($stats) {
            $efficiency = $stats->efficiencySummary($m);

            return [
                'motor' => $m,
                'avg_km_per_liter' => $efficiency['average'],
                'latest_km_per_liter' => $efficiency['latest'],
                'cost_per_km' => $stats->costPerKm($m),
            ];
        });

        $totalCost = $logs->sum('total_cost');

        return view('bbm.index', compact('motorcycles', 'logs', 'motorStats', 'totalCost'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'motorcycle_id' => 'required|exists:motorcycles,id',
            'filled_at' => 'required|date',
            'odometer_km' => 'required|integer|min:0',
            'liters' => 'required|numeric|min:0.1',
            'total_cost' => 'required|integer|min:0',
            'is_full_tank' => 'nullable|boolean',
            'note' => 'nullable|string|max:255',
        ]);

        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $data['is_full_tank'] = $request->boolean('is_full_tank', true);
        $motor->fuelLogs()->create($data);

        if ($data['odometer_km'] > $motor->current_odometer_km) {
            $motor->update(['current_odometer_km' => $data['odometer_km']]);
        }

        return redirect()->route('bbm.index')->with('status', 'Isi bensin dicatat.');
    }

    public function destroy(FuelLog $fuelLog)
    {
        abort_unless($fuelLog->motorcycle->user_id === auth()->id(), 403);
        $fuelLog->delete();

        return back()->with('status', 'Catatan BBM dihapus.');
    }
}
