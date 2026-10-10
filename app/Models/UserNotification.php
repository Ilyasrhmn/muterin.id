<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $fillable = ['user_id', 'event_key', 'severity', 'message', 'url', 'active', 'read_at'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'read_at' => 'datetime'];
    }
}
