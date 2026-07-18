<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['current_odometer_km'] = $data['initial_odometer_km'];
        auth()->user()->motorcycles()->create($data);

        return redirect()->route('motorcycles.index')->with('status', 'Motor ditambahkan.');
    }

    public function show(Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);
        $motorcycle->load('maintenanceItems.logs');

        return view('motorcycles.show', compact('motorcycle'));
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
}
