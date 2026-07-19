<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_item_creates_log_and_resets_checkpoint(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();

        $this->actingAs($user)->post(route('maintenance.complete', $oli), [
            'cost' => 45000, 'serviced_at' => '2026-07-19',
        ])->assertRedirect();

        $this->assertEquals(3000, $oli->fresh()->last_service_odometer_km);
        $this->assertDatabaseHas('maintenance_logs', [
            'maintenance_item_id' => $oli->id, 'serviced_at_odometer_km' => 3000, 'cost' => 45000,
        ]);
    }

    public function test_cannot_complete_item_of_other_users_motorcycle(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $owner->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $item = $motor->maintenanceItems()->first();

        $this->actingAs($intruder)->post(route('maintenance.complete', $item), [
            'serviced_at' => '2026-07-19',
        ])->assertForbidden();
    }

    public function test_completing_item_with_workshop_parts_and_receipt(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = \App\Models\User::factory()->create();
        $motor = \App\Models\Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();

        // ponytail: this GD build lacks JPEG support (no imagejpeg), use png so the fake image actually renders
        $file = \Illuminate\Http\UploadedFile::fake()->image('nota.png');

        $this->actingAs($user)->post(route('maintenance.complete', $oli), [
            'serviced_at' => '2026-07-19',
            'cost' => 50000,
            'workshop_name' => 'Bengkel Jaya Motor',
            'parts' => 'Oli Federal 0.8L, filter oli',
            'receipt' => $file,
        ])->assertRedirect();

        $log = $oli->logs()->latest()->first();
        $this->assertEquals('Bengkel Jaya Motor', $log->workshop_name);
        $this->assertEquals('Oli Federal 0.8L, filter oli', $log->parts);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($log->receipt_path);
    }

    public function test_receipt_upload_rejects_non_image(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = \App\Models\User::factory()->create();
        $motor = \App\Models\Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000,
        ]);
        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();

        $file = \Illuminate\Http\UploadedFile::fake()->create('nota.pdf', 100);

        $this->actingAs($user)->post(route('maintenance.complete', $oli), [
            'serviced_at' => '2026-07-19',
            'receipt' => $file,
        ])->assertSessionHasErrors('receipt');
    }
}
