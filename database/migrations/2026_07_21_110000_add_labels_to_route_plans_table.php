<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->string('start_label')->nullable()->after('duration_minutes');
            $table->string('end_label')->nullable()->after('start_label');
        });
    }

    public function down(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->dropColumn(['start_label', 'end_label']);
        });
    }
};
