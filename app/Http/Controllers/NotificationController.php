<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, UserNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return redirect()->to($notification->url);
    }

    public function readAll(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)->where('active', true)
            ->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
