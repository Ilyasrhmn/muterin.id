<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;

class NotificationCenterService
{
    public function __construct(private AttentionService $attention) {}

    public function forUser(User $user): Collection
    {
        $alerts = $this->attention->forUser($user->motorcycles()->with('maintenanceItems')->get());
        $keys = array_column($alerts, 'key');

        foreach ($alerts as $alert) {
            $notification = UserNotification::firstOrCreate(
                ['user_id' => $user->id, 'event_key' => $alert['key']],
                ['severity' => $alert['severity'], 'message' => $alert['text'], 'url' => $alert['url']],
            );
            $notification->fill(['severity' => $alert['severity'], 'message' => $alert['text'], 'url' => $alert['url'], 'active' => true]);
            if ($notification->getOriginal('active') === false) {
                $notification->read_at = null;
                $notification->created_at = now();
            }
            if ($notification->isDirty()) {
                $notification->save();
            }
        }

        UserNotification::where('user_id', $user->id)->where('active', true)
            ->whereNotIn('event_key', $keys)->update(['active' => false]);

        return UserNotification::where('user_id', $user->id)->where('active', true)
            ->orderByRaw('read_at IS NULL DESC')->orderByDesc('id')->get();
    }
}
