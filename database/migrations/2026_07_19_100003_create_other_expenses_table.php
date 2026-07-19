<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['asuransi', 'parkir', 'cuci_motor', 'aksesoris', 'lain_lain']);
            $table->unsignedInteger('amount');
            $table->date('expense_date');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_expenses');
    }
};
