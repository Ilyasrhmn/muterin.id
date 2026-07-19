<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Services\OdometerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OdometerReadingController extends Controller
{
    public function store(Request $request, OdometerService $odometer)
    {
        $data = $request->validate([
            'motorcycle_id' => 'required|exists:motorcycles,id',
            'reading_km' => 'required|integer|min:0',
            'recorded_at' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $odometer->record($motor, $data['reading_km'], Carbon::parse($data['recorded_at']), 'manual', $data['note'] ?? null);

        return back()->with('status', 'Odometer diperbarui.');
    }
}
