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

    public function test_storing_fuel_log_rejects_odometer_lower_than_current(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 5000]);

        $this->actingAs($user)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-19',
            'odometer_km' => 4900,
            'liters' => 3.0,
            'total_cost' => 45000,
        ])->assertSessionHasErrors('odometer_km');

        $this->assertEquals(5000, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseMissing('fuel_logs', ['motorcycle_id' => $motor->id, 'total_cost' => 45000]);
    }

    public function test_unrealistic_efficiency_flashes_a_warning(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $motor->fuelLogs()->create(['filled_at' => '2026-07-01', 'odometer_km' => 1000, 'liters' => 4, 'total_cost' => 60000, 'is_full_tank' => true]);

        // 500km on 2 liters = 250 km/l, well past the 60 km/l sanity threshold
        $response = $this->actingAs($user)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-10',
            'odometer_km' => 1500,
            'liters' => 2,
            'total_cost' => 30000,
            'is_full_tank' => '1',
        ]);

        $response->assertSessionHas('warning');
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

    public function test_cannot_delete_other_users_fuel_log(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $owner->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $log = $motor->fuelLogs()->create([
            'filled_at' => '2026-07-01', 'odometer_km' => 1000, 'liters' => 4, 'total_cost' => 60000,
        ]);

        $this->actingAs($intruder)->delete(route('bbm.destroy', $log))->assertForbidden();
    }
}
