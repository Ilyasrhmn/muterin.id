<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Models\OtherExpense;
use Illuminate\Http\Request;

class OtherExpenseController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'motorcycle_id' => 'required|exists:motorcycles,id',
            'category' => 'required|in:asuransi,parkir,cuci_motor,aksesoris,lain_lain',
            'amount' => 'required|integer|min:0',
            'expense_date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $motor->otherExpenses()->create($data);

        return back()->with('status', 'Pengeluaran dicatat.');
    }

    public function destroy(OtherExpense $otherExpense)
    {
        abort_unless($otherExpense->motorcycle->user_id === auth()->id(), 403);
        $otherExpense->delete();

        return back()->with('status', 'Pengeluaran dihapus.');
    }
}
