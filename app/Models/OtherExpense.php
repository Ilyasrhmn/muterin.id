<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtherExpense extends Model
{
    protected $fillable = ['motorcycle_id', 'category', 'amount', 'expense_date', 'note'];

    protected $casts = ['expense_date' => 'date'];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }
}
