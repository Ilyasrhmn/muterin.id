<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_storing_fuel_log_updates_odometer_if_higher(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($user)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-19',
            'odometer_km' => 1200,
            'liters' => 4.5,
            'total_cost' => 65000,
            'is_full_tank' => '1',
        ])->assertRedirect(route('bbm.index'));

        $this->assertEquals(1200, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('fuel_logs', ['motorcycle_id' => $motor->id, 'total_cost' => 65000]);
    }

    public function test_storing_fuel_log_does_not_lower_odometer(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 5000]);

        $this->actingAs($user)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-19',
            'odometer_km' => 4900,
            'liters' => 3.0,
            'total_cost' => 45000,
        ]);

        $this->assertEquals(5000, $motor->fresh()->current_odometer_km);
    }

    public function test_cannot_store_fuel_log_for_other_users_motorcycle(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $owner->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($intruder)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-19',
            'odometer_km' => 1200,
            'liters' => 4.5,
            'total_cost' => 65000,
        ])->assertForbidden();
    }

    public function test_can_delete_own_fuel_log(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $log = $motor->fuelLogs()->create([
            'filled_at' => '2026-07-01', 'odometer_km' => 1000, 'liters' => 4, 'total_cost' => 60000,
        ]);

        $this->actingAs($user)->delete(route('bbm.destroy', $log))->assertRedirect();
        $this->assertDatabaseMissing('fuel_logs', ['id' => $log->id]);
    }
}
