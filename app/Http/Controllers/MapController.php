<?php

namespace App\Http\Controllers;

use App\Models\MapPin;
use App\Models\RoutePlan;
use App\Models\Trip;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index()
    {
        return view('map.index');
    }

    public function data()
    {
        $userId = auth()->id();

        return response()->json([
            'pins' => MapPin::where('user_id', $userId)->get(),
            'plans' => RoutePlan::where('user_id', $userId)->get(),
            'trips' => Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))
                ->whereNotNull('path_json')->get(['id', 'path_json']),
        ]);
    }

    public function storePin(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|in:moment,hazard,quiet',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'title' => 'required|string|max:255',
            'note' => 'nullable|string|max:255',
        ]);
        $pin = auth()->user()->mapPins()->create($data);

        return response()->json($pin, 201);
    }

    public function destroyPin(MapPin $pin)
    {
        abort_unless($pin->user_id === auth()->id(), 403);
        $pin->delete();

        return response()->json(['ok' => true]);
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'points' => 'required|array|min:2',
        ]);
        $plan = auth()->user()->routePlans()->create([
            'name' => $data['name'],
            'points_json' => $data['points'],
        ]);

        return response()->json($plan, 201);
    }
}
