<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    private function motor(User $user): Motorcycle
    {
        return Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 1000, 'current_odometer_km' => 1000,
        ]);
    }

    public function test_start_creates_recording_draft_without_touching_odometer(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);

        $tripId = $this->actingAs($user)->postJson(route('trips.start'), [
            'motorcycle_id' => $motor->id,
        ])->assertCreated()->json('trip_id');

        $this->assertDatabaseHas('trips', ['id' => $tripId, 'status' => 'recording']);
        $this->assertEquals(1000, $motor->fresh()->current_odometer_km);
    }

    public function test_checkpoint_updates_draft_without_touching_odometer(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 0, 'duration_seconds' => 0, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->patchJson(route('trips.checkpoint', $trip), [
            'distance_km' => 4.2, 'duration_seconds' => 300, 'path' => [[-6.2, 106.8], [-6.21, 106.81]],
        ])->assertOk();

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'distance_km' => 4.2, 'status' => 'recording']);
        $this->assertEquals(1000, $motor->fresh()->current_odometer_km);
    }

    public function test_finish_completes_trip_and_increments_odometer_once(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 0, 'duration_seconds' => 0, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->patchJson(route('trips.finish', $trip), [
            'distance_km' => 12.5, 'duration_seconds' => 1800, 'path' => [[-6.2, 106.8], [-6.21, 106.81]],
        ])->assertOk();

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'completed', 'distance_km' => 12.5]);
        $this->assertEquals(1013, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 1013, 'source' => 'trip']);
    }

    public function test_finish_is_idempotent_for_odometer(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 0, 'duration_seconds' => 0, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->patchJson(route('trips.finish', $trip), ['distance_km' => 12.5, 'duration_seconds' => 1800]);
        $this->actingAs($user)->patchJson(route('trips.finish', $trip), ['distance_km' => 12.5, 'duration_seconds' => 1800])->assertOk();

        // Odometer only moved once (1000 -> 1013), not twice.
        $this->assertEquals(1013, $motor->fresh()->current_odometer_km);
        $this->assertEquals(1, $motor->odometerReadings()->where('source', 'trip')->count());
    }

    public function test_destroy_deletes_a_recording_draft(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 1, 'duration_seconds' => 60, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->deleteJson(route('trips.destroy', $trip))->assertOk();
        $this->assertDatabaseMissing('trips', ['id' => $trip->id]);
    }

    public function test_destroy_refuses_a_completed_trip(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 5, 'duration_seconds' => 300, 'status' => 'completed', 'ended_at' => now()]);

        $this->actingAs($user)->deleteJson(route('trips.destroy', $trip))->assertStatus(422);
        $this->assertDatabaseHas('trips', ['id' => $trip->id]);
    }

    public function test_lifecycle_endpoints_enforce_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherMotor = Motorcycle::create(['user_id' => $other->id, 'nickname' => 'X']);
        $otherTrip = $otherMotor->trips()->create(['distance_km' => 1, 'duration_seconds' => 60, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->postJson(route('trips.start'), ['motorcycle_id' => $otherMotor->id])->assertForbidden();
        $this->actingAs($user)->patchJson(route('trips.checkpoint', $otherTrip), ['distance_km' => 1, 'duration_seconds' => 1])->assertForbidden();
        $this->actingAs($user)->patchJson(route('trips.finish', $otherTrip), ['distance_km' => 1, 'duration_seconds' => 1])->assertForbidden();
        $this->actingAs($user)->deleteJson(route('trips.destroy', $otherTrip))->assertForbidden();
    }

    public function test_riding_page_shows_recovery_banner_for_unfinished_trip(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $motor->trips()->create(['distance_km' => 3.4, 'duration_seconds' => 200, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->get(route('riding'))
            ->assertOk()
            ->assertSee('Ada perjalanan yang belum selesai')
            ->assertSee('data-recover-trip', false);
    }

    public function test_riding_page_has_no_banner_without_unfinished_trip(): void
    {
        $user = User::factory()->create();
        $this->motor($user);

        $this->actingAs($user)->get(route('riding'))->assertDontSee('Ada perjalanan yang belum selesai');
    }
}
