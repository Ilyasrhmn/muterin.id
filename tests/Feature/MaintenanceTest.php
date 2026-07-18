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
}
