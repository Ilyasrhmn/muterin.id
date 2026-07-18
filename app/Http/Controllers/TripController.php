<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function create()
    {
        $motorcycles = auth()->user()->motorcycles()->get();

        return view('riding.index', compact('motorcycles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'motorcycle_id' => 'required|exists:motorcycles,id',
            'distance_km' => 'required|numeric|min:0',
            'duration_seconds' => 'required|integer|min:0',
            'path' => 'nullable|array',
        ]);

        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $trip = $motor->trips()->create([
            'distance_km' => $data['distance_km'],
            'duration_seconds' => $data['duration_seconds'],
            'path_json' => $data['path'] ?? null,
            'started_at' => now()->subSeconds($data['duration_seconds']),
            'ended_at' => now(),
        ]);
        $motor->increment('current_odometer_km', (int) round($data['distance_km']));

        return response()->json(['ok' => true, 'trip_id' => $trip->id], 201);
    }
}
