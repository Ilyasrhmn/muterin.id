<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    public function test_finishing_trip_increments_odometer(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 1000, 'current_odometer_km' => 1000,
        ]);

        $this->actingAs($user)->postJson(route('trips.store'), [
            'motorcycle_id' => $motor->id,
            'distance_km' => 12.5,
            'duration_seconds' => 1800,
            'path' => [[-6.2, 106.8], [-6.21, 106.81]],
        ])->assertCreated();

        $this->assertEquals(1013, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('trips', ['motorcycle_id' => $motor->id, 'distance_km' => 12.5]);
    }

    public function test_cannot_add_trip_to_other_users_motorcycle(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $other->id, 'nickname' => 'X']);

        $this->actingAs($user)->postJson(route('trips.store'), [
            'motorcycle_id' => $motor->id, 'distance_km' => 5, 'duration_seconds' => 60,
        ])->assertForbidden();
    }

    public function test_finishing_trip_records_an_odometer_reading(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 1000, 'current_odometer_km' => 1000,
        ]);

        $this->actingAs($user)->postJson(route('trips.store'), [
            'motorcycle_id' => $motor->id,
            'distance_km' => 12.5,
            'duration_seconds' => 1800,
        ]);

        $this->assertDatabaseHas('odometer_readings', [
            'motorcycle_id' => $motor->id, 'reading_km' => 1013, 'source' => 'trip',
        ]);
    }
}
