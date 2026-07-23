<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPlace extends Model
{
    protected $fillable = ['user_id', 'place_list_id', 'lat', 'lng', 'title', 'description', 'photo_path'];

    protected $casts = ['lat' => 'float', 'lng' => 'float'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(PlaceList::class, 'place_list_id');
    }
}
