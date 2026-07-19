<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_motorcycle_has_fuel_logs_relation(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000,
        ]);

        $motor->fuelLogs()->create([
            'filled_at' => '2026-07-01',
            'odometer_km' => 1000,
            'liters' => 4.5,
            'total_cost' => 65000,
            'is_full_tank' => true,
        ]);

        $this->assertCount(1, $motor->fuelLogs);
        $this->assertEquals(4.5, $motor->fuelLogs->first()->liters);
    }
}
