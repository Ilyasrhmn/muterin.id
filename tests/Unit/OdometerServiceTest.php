<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\OdometerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OdometerServiceTest extends TestCase
{
    use RefreshDatabase;

    private OdometerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new OdometerService();
    }

    public function test_record_creates_reading_and_raises_current_odometer(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $reading = $this->svc->record($motor, 1200, Carbon::parse('2026-07-19'), 'manual', 'test note');

        $this->assertEquals(1200, $reading->reading_km);
        $this->assertEquals('manual', $reading->source);
        $this->assertEquals(1200, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 1200, 'note' => 'test note']);
    }

    public function test_record_at_same_km_does_not_error_and_still_logs_reading(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->svc->record($motor, 1000, Carbon::parse('2026-07-19'), 'fuel');

        $this->assertEquals(1000, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 1000]);
    }

    public function test_record_rejects_odometer_lower_than_current(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 5000]);

        $this->expectException(ValidationException::class);

        try {
            $this->svc->record($motor, 4900, Carbon::parse('2026-07-19'), 'manual');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('odometer_km', $e->errors());
            $this->assertEquals(5000, $motor->fresh()->current_odometer_km);
            $this->assertDatabaseMissing('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 4900]);
            throw $e;
        }
    }

    public function test_avg_km_per_day_from_recent_readings_within_30_days(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $motor->odometerReadings()->create(['reading_km' => 940, 'recorded_at' => now()->subDays(10), 'source' => 'manual']);
        $motor->odometerReadings()->create(['reading_km' => 1000, 'recorded_at' => now()->subDays(5), 'source' => 'manual']);

        // delta 60 km, fixed /30 divisor -> 2.0 km/day
        $this->assertEquals(2.0, $this->svc->avgKmPerDay($motor));
    }

    public function test_avg_km_per_day_falls_back_to_lifetime_when_no_recent_readings(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 1000, 'current_odometer_km' => 1000,
        ]);

        $this->assertNull($this->svc->avgKmPerDay($motor));
    }
}
