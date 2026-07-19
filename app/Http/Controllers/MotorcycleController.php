<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Services\MaintenanceStatusService;
use App\Services\OdometerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MotorcycleController extends Controller
{
    public function index()
    {
        $motorcycles = auth()->user()->motorcycles()->with('maintenanceItems')->get();

        return view('motorcycles.index', compact('motorcycles'));
    }

    public function create()
    {
        return view('motorcycles.create');
    }

    public function store(Request $request, OdometerService $odometer)
    {
        $data = $this->validated($request);
        $onboarding = $this->onboardingChecklist($request);

        $data['current_odometer_km'] = $data['initial_odometer_km'];
        $motorcycle = auth()->user()->motorcycles()->create($data);

        $odometer->record($motorcycle, $data['initial_odometer_km'], Carbon::today(), 'initial');
        $this->applyOnboardingChecklist($motorcycle, $onboarding);

        return redirect()->route('motorcycles.index')->with('status', 'Motor ditambahkan.');
    }

    public function show(Motorcycle $motorcycle, MaintenanceStatusService $status)
    {
        $this->authorizeOwner($motorcycle);
        $motorcycle->load('maintenanceItems.logs');

        $items = $motorcycle->maintenanceItems->map(fn ($item) => [
            'item' => $item,
            'status' => $status->forItem($item, $motorcycle->current_odometer_km),
        ]);

        return view('motorcycles.show', compact('motorcycle', 'items'));
    }

    public function edit(Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);

        return view('motorcycles.edit', compact('motorcycle'));
    }

    public function update(Request $request, Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);
        $motorcycle->update($this->validated($request));

        return redirect()->route('motorcycles.show', $motorcycle)->with('status', 'Motor diperbarui.');
    }

    public function destroy(Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);
        $motorcycle->delete();

        return redirect()->route('motorcycles.index')->with('status', 'Motor dihapus.');
    }

    public function activate(Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);
        auth()->user()->motorcycles()->update(['is_active' => false]);
        $motorcycle->update(['is_active' => true]);

        return back()->with('status', "Motor aktif: {$motorcycle->nickname}");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nickname' => 'required|string|max:255',
            'plat_nomor' => 'required|string|max:20',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1980|max:2100',
            'initial_odometer_km' => 'required|integer|min:0',
        ]);
    }

    private function authorizeOwner(Motorcycle $motorcycle): void
    {
        abort_unless($motorcycle->user_id === auth()->id(), 403);
    }

    private function onboardingChecklist(Request $request): array
    {
        return $request->validate([
            'oli_last_km' => 'nullable|integer|min:0',
            'ban_last_km' => 'nullable|integer|min:0',
            'aki_last_km' => 'nullable|integer|min:0',
            'servis_last_km' => 'nullable|integer|min:0',
        ]);
    }

    private function applyOnboardingChecklist(Motorcycle $motorcycle, array $checklist): void
    {
        $map = [
            'oli_last_km' => 'Oli Mesin',
            'ban_last_km' => 'Ban',
            'aki_last_km' => 'Aki',
            'servis_last_km' => 'Servis Rutin',
        ];

        foreach ($map as $field => $itemName) {
            if (!empty($checklist[$field])) {
                $motorcycle->maintenanceItems()
                    ->where('name', $itemName)
                    ->update(['last_service_odometer_km' => $checklist[$field]]);
            }
        }
    }
}
