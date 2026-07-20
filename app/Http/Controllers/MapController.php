<?php

namespace App\Http\Controllers;

use App\Exceptions\RouteNotFoundException;
use App\Models\MapPin;
use App\Models\RoutePlan;
use App\Models\Trip;
use App\Services\RouteService;
use Illuminate\Http\Request;

class MapController extends Controller
{
    // --- Peta Rute: read-only view of recorded trip paths ---
    public function routesPage()
    {
        $trips = Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', auth()->id()))
            ->whereNotNull('path_json')
            ->with('motorcycle')
            ->latest('ended_at')
            ->get();

        return view('map.routes', compact('trips'));
    }

    // --- Titik Saya: manage moment/hazard/quiet pins ---
    public function pinsPage()
    {
        $pins = auth()->user()->mapPins()->latest()->get();

        return view('map.pins', compact('pins'));
    }

    // --- Rencana Rute: build & manage saved route plans ---
    public function plansPage()
    {
        $plans = auth()->user()->routePlans()->latest()->get();

        return view('map.plans', compact('plans'));
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

    public function previewRoute(Request $request, RouteService $routing)
    {
        $data = $request->validate([
            'waypoints' => 'required|array|min:2',
            'waypoints.*' => 'required|array|size:2',
            'waypoints.*.0' => 'required|numeric|between:-90,90',
            'waypoints.*.1' => 'required|numeric|between:-180,180',
        ]);

        try {
            return response()->json($routing->route($data['waypoints']));
        } catch (RouteNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
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
            'route_geometry' => 'required|array|min:2',
            'distance_km' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:0',
        ]);
        $plan = auth()->user()->routePlans()->create([
            'name' => $data['name'],
            'points_json' => $data['points'],
            'route_geometry_json' => $data['route_geometry'],
            'distance_km' => $data['distance_km'],
            'duration_minutes' => $data['duration_minutes'],
        ]);

        return response()->json($plan, 201);
    }

    public function destroyPlan(RoutePlan $plan)
    {
        abort_unless($plan->user_id === auth()->id(), 403);
        $plan->delete();

        return response()->json(['ok' => true]);
    }
}
