<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->string('workshop_name')->nullable()->after('note');
            $table->string('parts')->nullable()->after('workshop_name');
            $table->string('receipt_path')->nullable()->after('parts');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropColumn(['workshop_name', 'parts', 'receipt_path']);
        });
    }
};
