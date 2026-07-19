<?php

namespace Tests\Unit;

use App\Models\MaintenanceItem;
use App\Models\Motorcycle;
use App\Models\User;
use App\Services\MaintenancePredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private MaintenancePredictionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new MaintenancePredictionService();
    }

    public function test_avg_km_per_day_from_recent_trips(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $motor->trips()->create([
            'distance_km' => 60, 'duration_seconds' => 3600,
            'started_at' => now()->subDays(5), 'ended_at' => now()->subDays(5),
        ]);

        // 60 km total over the fixed 30-day window = 2.0 km/day
        $this->assertEquals(2.0, $this->svc->avgKmPerDay($motor));
    }

    public function test_avg_km_per_day_falls_back_to_lifetime_when_no_recent_trips(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 1000, 'current_odometer_km' => 1000,
        ]);
        // No trips at all, and no km traveled since creation -> null
        $this->assertNull($this->svc->avgKmPerDay($motor));
    }

    public function test_for_item_calculates_days_left_and_predicted_date(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $item = MaintenanceItem::create([
            'motorcycle_id' => $motor->id, 'name' => 'Oli Mesin',
            'interval_km' => 2500, 'last_service_odometer_km' => 1000,
        ]);

        // used=0, remaining=2500, avg 250 km/day -> 10 days left
        $result = $this->svc->forItem($item, 1000, 250.0);

        $this->assertEquals(10, $result['days_left']);
        $this->assertEquals(now()->addDays(10)->toDateString(), $result['predicted_date']->toDateString());
    }

    public function test_for_item_returns_null_without_avg_km_per_day(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $item = MaintenanceItem::create([
            'motorcycle_id' => $motor->id, 'name' => 'Oli Mesin',
            'interval_km' => 2500, 'last_service_odometer_km' => 1000,
        ]);

        $result = $this->svc->forItem($item, 1000, null);

        $this->assertNull($result['days_left']);
        $this->assertNull($result['predicted_date']);
    }

    public function test_for_item_zero_days_left_when_already_overdue(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 4000]);
        $item = MaintenanceItem::create([
            'motorcycle_id' => $motor->id, 'name' => 'Oli Mesin',
            'interval_km' => 2500, 'last_service_odometer_km' => 1000,
        ]);

        // used=3000 > interval=2500 -> remaining clamped to 0 by MaintenanceStatusService -> 0 days left
        $result = $this->svc->forItem($item, 4000, 100.0);

        $this->assertEquals(0, $result['days_left']);
    }
}
