<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorcycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_motorcycle_seeds_four_default_items(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id,
            'nickname' => 'Beat Merah',
            'initial_odometer_km' => 10000,
            'current_odometer_km' => 10000,
        ]);

        $this->assertCount(4, $motor->maintenanceItems);
        $this->assertEqualsCanonicalizing(
            ['Oli Mesin', 'Ban', 'Aki', 'Servis Rutin'],
            $motor->maintenanceItems->pluck('name')->all()
        );
        $this->assertEquals(10000, $motor->maintenanceItems->firstWhere('name', 'Oli Mesin')->last_service_odometer_km);
    }
}
