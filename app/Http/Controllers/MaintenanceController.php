<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceItem;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function complete(Request $request, MaintenanceItem $item)
    {
        abort_unless($item->motorcycle->user_id === auth()->id(), 403);

        $data = $request->validate([
            'cost' => 'nullable|integer|min:0',
            'serviced_at' => 'required|date',
            'note' => 'nullable|string|max:255',
            'workshop_name' => 'nullable|string|max:255',
            'parts' => 'nullable|string|max:255',
            'receipt' => 'nullable|image|max:2048',
        ]);

        $receiptPath = $request->hasFile('receipt')
            ? $request->file('receipt')->store('receipts', 'public')
            : null;

        $odometer = $item->motorcycle->current_odometer_km;
        $item->logs()->create([
            'serviced_at_odometer_km' => $odometer,
            'cost' => $data['cost'] ?? null,
            'serviced_at' => $data['serviced_at'],
            'note' => $data['note'] ?? null,
            'workshop_name' => $data['workshop_name'] ?? null,
            'parts' => $data['parts'] ?? null,
            'receipt_path' => $receiptPath,
        ]);
        $item->update(['last_service_odometer_km' => $odometer]);

        return back()->with('status', "{$item->name} ditandai selesai di {$odometer} km.");
    }
}
