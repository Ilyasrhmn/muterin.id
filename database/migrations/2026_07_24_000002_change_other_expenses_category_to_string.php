<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('other_expenses', function (Blueprint $table) {
            $table->string('category', 50)->change();
        });

        $map = [
            'asuransi' => 'Asuransi',
            'parkir' => 'Parkir',
            'cuci_motor' => 'Cuci Motor',
            'aksesoris' => 'Aksesoris',
            'lain_lain' => 'Lain-lain',
        ];
        foreach ($map as $key => $label) {
            DB::table('other_expenses')->where('category', $key)->update(['category' => $label]);
        }
    }

    public function down(): void
    {
        $map = [
            'Asuransi' => 'asuransi',
            'Parkir' => 'parkir',
            'Cuci Motor' => 'cuci_motor',
            'Aksesoris' => 'aksesoris',
            'Lain-lain' => 'lain_lain',
        ];
        foreach ($map as $label => $key) {
            DB::table('other_expenses')->where('category', $label)->update(['category' => $key]);
        }
        Schema::table('other_expenses', function (Blueprint $table) {
            $table->enum('category', ['asuransi', 'parkir', 'cuci_motor', 'aksesoris', 'lain_lain'])->change();
        });
    }
};
