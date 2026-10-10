<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_id')->unique();
            $table->string('plan');
            $table->unsignedInteger('amount');
            $table->string('environment');
            $table->string('status')->default('pending');
            $table->string('provider_status')->nullable();
            $table->string('payment_type')->nullable();
            $table->text('snap_token')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
