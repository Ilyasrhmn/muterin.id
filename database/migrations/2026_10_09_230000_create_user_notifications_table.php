<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->string('severity');
            $table->text('message');
            $table->text('url');
            $table->boolean('active')->default(true);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event_key']);
            $table->index(['user_id', 'active', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
