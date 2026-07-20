<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->json('route_geometry_json')->nullable()->after('points_json');
            $table->decimal('distance_km', 8, 2)->nullable()->after('route_geometry_json');
            $table->unsignedInteger('duration_minutes')->nullable()->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->dropColumn(['route_geometry_json', 'distance_km', 'duration_minutes']);
        });
    }
};
