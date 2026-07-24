<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseCategory extends Model
{
    public const DEFAULTS = ['Asuransi', 'Parkir', 'Cuci Motor', 'Aksesoris', 'Lain-lain'];

    protected $fillable = ['user_id', 'name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Buat kategori bawaan untuk user kalau belum ada. Idempotent.
    public static function ensureDefaultsFor(User $user): void
    {
        foreach (self::DEFAULTS as $name) {
            $user->expenseCategories()->firstOrCreate(['name' => $name]);
        }
    }
}
