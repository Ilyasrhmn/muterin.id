<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        ExpenseCategory::ensureDefaultsFor(auth()->user());

        return view('expense-categories.index', [
            'categories' => auth()->user()->expenseCategories()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('expense_categories')->where('user_id', auth()->id())],
        ]);
        auth()->user()->expenseCategories()->create($data);

        return back()->with('status', 'Kategori ditambahkan.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        abort_unless($expenseCategory->user_id === auth()->id(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('expense_categories')->where('user_id', auth()->id())->ignore($expenseCategory->id)],
        ]);
        $expenseCategory->update($data);

        return back()->with('status', 'Kategori diperbarui.');
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        abort_unless($expenseCategory->user_id === auth()->id(), 403);
        $expenseCategory->delete();

        return back()->with('status', 'Kategori dihapus.');
    }
}
