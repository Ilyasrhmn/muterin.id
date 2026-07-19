<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\FuelStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private FuelStatsService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new FuelStatsService();
    }

    private function motorWithFuel(array $fills): Motorcycle
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 0]);

        foreach ($fills as [$date, $km, $liters, $cost, $full]) {
            $motor->fuelLogs()->create([
                'filled_at' => $date, 'odometer_km' => $km, 'liters' => $liters,
                'total_cost' => $cost, 'is_full_tank' => $full,
            ]);
        }

        return $motor->fresh();
    }

    public function test_consumption_series_only_counts_full_to_full(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
            ['2026-07-05', 1050, 3.0, 45000, false], // partial fill, skipped as endpoint
            ['2026-07-10', 1150, 5.0, 75000, true],
        ]);

        $series = $this->svc->consumptionSeries($motor);

        // Only the full(1000) -> full(1150) pair counts: (1150-1000)/5.0 = 30.0
        $this->assertCount(1, $series);
        $this->assertEquals(30.0, $series[0]['km_per_liter']);
    }

    public function test_average_and_latest_km_per_liter(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
            ['2026-07-05', 1100, 5.0, 75000, true],
            ['2026-07-10', 1180, 4.0, 60000, true],
        ]);

        // 1000->1100 = 20.0 km/l, 1100->1180 = 20.0 km/l
        $this->assertEquals(20.0, $this->svc->averageKmPerLiter($motor));
        $this->assertEquals(20.0, $this->svc->latestKmPerLiter($motor));
    }

    public function test_null_when_not_enough_full_tank_data(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
        ]);

        $this->assertNull($this->svc->averageKmPerLiter($motor));
        $this->assertNull($this->svc->latestKmPerLiter($motor));
    }

    public function test_cost_per_km(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
            ['2026-07-10', 1200, 5.0, 75000, true],
        ]);

        // total cost 135000 / distance (1200-1000)=200 = 675
        $this->assertEquals(675.0, $this->svc->costPerKm($motor));
    }

    public function test_cost_per_km_null_with_single_log(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
        ]);

        $this->assertNull($this->svc->costPerKm($motor));
    }
}
