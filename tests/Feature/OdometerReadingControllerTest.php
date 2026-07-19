<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdometerReadingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_manually_update_odometer(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($user)->post(route('odometer.store'), [
            'motorcycle_id' => $motor->id,
            'reading_km' => 1250,
            'recorded_at' => '2026-07-19',
            'note' => 'cek rutin',
        ])->assertRedirect();

        $this->assertEquals(1250, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 1250, 'source' => 'manual']);
    }

    public function test_cannot_update_odometer_of_other_users_motorcycle(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $owner->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($intruder)->post(route('odometer.store'), [
            'motorcycle_id' => $motor->id,
            'reading_km' => 1250,
            'recorded_at' => '2026-07-19',
        ])->assertForbidden();
    }
}
