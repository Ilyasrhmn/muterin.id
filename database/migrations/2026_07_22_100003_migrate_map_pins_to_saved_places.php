<?php

use App\Models\PlaceList;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('map_pins')) {
            return;
        }

        foreach (DB::table('map_pins')->get() as $pin) {
            $user = User::find($pin->user_id);
            if (! $user) {
                continue;
            }
            PlaceList::ensureDefaultsFor($user);
            $favorit = $user->placeLists()->where('name', 'Favorit')->where('is_default', true)->first();
            if (! $favorit) {
                continue;
            }
            $user->savedPlaces()->create([
                'place_list_id' => $favorit->id,
                'lat' => $pin->lat,
                'lng' => $pin->lng,
                'title' => $pin->title,
                'description' => $pin->note,
            ]);
        }

        Schema::dropIfExists('map_pins');
    }

    public function down(): void
    {
        // Retirement satu arah; tidak me-recreate map_pins.
    }
};
