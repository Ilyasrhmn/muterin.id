<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['sepi', 'gelap', 'rawan', 'rusak', 'banjir', 'momen']);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('time_context', ['siang', 'malam', 'kapanpun'])->default('kapanpun');
            $table->boolean('is_anonymous')->default(false);
            $table->integer('confirm_count')->default(0); // signed: "masih" - "udah nggak"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_pins');
    }
};
