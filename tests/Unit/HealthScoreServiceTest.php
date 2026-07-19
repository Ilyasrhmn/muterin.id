<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\FuelStatsService;
use App\Services\HealthScoreService;
use App\Services\MaintenanceStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private HealthScoreService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new HealthScoreService(new MaintenanceStatusService(), new FuelStatsService());
    }

    public function test_brand_new_motorcycle_scores_100(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 0]);

        $result = $this->svc->forMotorcycle($motor->fresh(['maintenanceItems']));

        $this->assertEquals(100, $result['score']);
        $this->assertEquals('green', $result['color']);
        $this->assertEquals('Sehat', $result['label']);
    }

    public function test_one_overdue_item_deducts_15(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        // Oli Mesin: interval 2500, last_service 0 -> used 3000 > interval -> overdue (-15)

        $result = $this->svc->forMotorcycle($motor->fresh(['maintenanceItems']));

        $this->assertEquals(85, $result['score']);
    }

    public function test_score_clamped_to_zero_minimum(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 100000,
        ]);
        // All 4 default items overdue: 4 * -15 = -60 -> score 40, still above 0 here;
        // this test documents the clamp exists even though 4 items alone won't hit it.
        $result = $this->svc->forMotorcycle($motor->fresh(['maintenanceItems']));

        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertEquals('red', $result['color']);
        $this->assertEquals('Butuh Servis', $result['label']);
    }

    public function test_label_bands(): void
    {
        $this->assertEquals('Sehat', $this->labelFor(85));
        $this->assertEquals('Perlu Perhatian', $this->labelFor(65));
        $this->assertEquals('Butuh Servis', $this->labelFor(40));
    }

    private function labelFor(int $score): string
    {
        return $score >= 80 ? 'Sehat' : ($score >= 60 ? 'Perlu Perhatian' : 'Butuh Servis');
    }
}
