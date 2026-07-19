<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motorcycles', function (Blueprint $table) {
            $table->date('stnk_due_date')->nullable()->after('plat_nomor');
            $table->date('plat_due_date')->nullable()->after('stnk_due_date');
            $table->date('insurance_due_date')->nullable()->after('plat_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('motorcycles', function (Blueprint $table) {
            $table->dropColumn(['stnk_due_date', 'plat_due_date', 'insurance_due_date']);
        });
    }
};
