<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_pins', function (Blueprint $table) {
            $table->unsignedInteger('still_count')->default(0)->after('confirm_count');
            $table->unsignedInteger('gone_count')->default(0)->after('still_count');
        });

        // Backfill dari confirmations yang sudah ada.
        DB::table('community_pins')->orderBy('id')->each(function ($pin) {
            $still = DB::table('community_pin_confirmations')
                ->where('community_pin_id', $pin->id)->where('still_there', true)->count();
            $gone = DB::table('community_pin_confirmations')
                ->where('community_pin_id', $pin->id)->where('still_there', false)->count();
            DB::table('community_pins')->where('id', $pin->id)->update([
                'still_count' => $still, 'gone_count' => $gone,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('community_pins', function (Blueprint $table) {
            $table->dropColumn(['still_count', 'gone_count']);
        });
    }
};
