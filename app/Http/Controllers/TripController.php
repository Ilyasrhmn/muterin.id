<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Models\Trip;
use App\Services\OdometerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    public function create()
    {
        $motorcycles = auth()->user()->motorcycles()->get();
        $unfinished = Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', auth()->id()))
            ->where('status', 'recording')
            ->with('motorcycle')
            ->latest()
            ->first();

        return view('riding.index', compact('motorcycles', 'unfinished'));
    }

    public function start(Request $request)
    {
        $data = $request->validate(['motorcycle_id' => 'required|exists:motorcycles,id']);
        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $trip = $motor->trips()->create([
            'distance_km' => 0,
            'duration_seconds' => 0,
            'status' => 'recording',
            'path_json' => [],
            'started_at' => now(),
        ]);

        return response()->json(['trip_id' => $trip->id], 201);
    }

    public function checkpoint(Request $request, Trip $trip)
    {
        $this->authorizeTrip($trip);
        $data = $this->validatedProgress($request);

        // Only a recording draft accepts checkpoints; ignore silently if already finished.
        if ($trip->status === 'recording') {
            $trip->update([
                'distance_km' => $data['distance_km'],
                'duration_seconds' => $data['duration_seconds'],
                'path_json' => $data['path'] ?? $trip->path_json,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function finish(Request $request, Trip $trip, OdometerService $odometer)
    {
        $this->authorizeTrip($trip);
        $data = $this->validatedProgress($request);

        // ponytail: lockForUpdate makes the recording-check-then-write atomic against
        // concurrent finish requests for the same trip (e.g. a retried request after a
        // dropped response), so the odometer can never be double-counted.
        DB::transaction(function () use ($trip, $data, $odometer) {
            $locked = Trip::whereKey($trip->id)->lockForUpdate()->first();

            if ($locked->status === 'recording') {
                $locked->update([
                    'distance_km' => $data['distance_km'],
                    'duration_seconds' => $data['duration_seconds'],
                    'path_json' => $data['path'] ?? $locked->path_json,
                    'status' => 'completed',
                    'ended_at' => now(),
                ]);

                $motor = $locked->motorcycle;
                $newOdometer = $motor->current_odometer_km + (int) round($data['distance_km']);
                $odometer->record($motor, $newOdometer, now(), 'trip');
            }
        });

        return response()->json(['ok' => true, 'trip_id' => $trip->id]);
    }

    public function destroy(Trip $trip)
    {
        $this->authorizeTrip($trip);
        abort_if($trip->status !== 'recording', 422, 'Hanya perjalanan yang belum selesai yang bisa dibuang.');
        $trip->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeTrip(Trip $trip): void
    {
        abort_unless($trip->motorcycle->user_id === auth()->id(), 403);
    }

    private function validatedProgress(Request $request): array
    {
        return $request->validate([
            'distance_km' => 'required|numeric|min:0',
            'duration_seconds' => 'required|integer|min:0',
            'path' => 'nullable|array',
        ]);
    }
}
