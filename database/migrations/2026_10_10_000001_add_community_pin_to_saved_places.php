<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_places', function (Blueprint $table) {
            $table->foreignId('community_pin_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['user_id', 'community_pin_id']);
        });
    }

    public function down(): void
    {
        Schema::table('saved_places', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'community_pin_id']);
            $table->dropConstrainedForeignId('community_pin_id');
        });
    }
};
