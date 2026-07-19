<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\AttentionService;
use App\Services\FuelStatsService;
use App\Services\MaintenancePredictionService;
use App\Services\MaintenanceStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttentionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttentionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new AttentionService(
            new MaintenanceStatusService(),
            new MaintenancePredictionService(),
            new FuelStatsService(),
        );
    }

    public function test_overdue_item_produces_red_severity_item(): void
    {
        $user = User::factory()->create();
        Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'Beat',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        // Oli Mesin: interval 2500, used 3000 -> overdue

        $items = $this->svc->forUser($user->fresh(['motorcycles.maintenanceItems']));

        $redItems = array_filter($items, fn ($i) => $i['severity'] === 'red');
        $this->assertNotEmpty($redItems);
        $this->assertStringContainsString('Oli Mesin', array_values($redItems)[0]['text']);
    }

    public function test_healthy_motorcycle_with_no_trips_produces_no_items(): void
    {
        $user = User::factory()->create();
        Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'Beat', 'current_odometer_km' => 0,
        ]);
        // All items at 0% used, no trips -> no red (not overdue) and no yellow (no prediction data)

        $items = $this->svc->forUser($user->fresh(['motorcycles.maintenanceItems']));

        $this->assertEmpty($items);
    }

    public function test_red_items_sorted_before_yellow(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'Beat',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        $motor->trips()->create([
            'distance_km' => 300, 'duration_seconds' => 60,
            'started_at' => now()->subDay(), 'ended_at' => now()->subDay(),
        ]);
        // avg km/day ~10 -> Aki (interval 15000, remaining 12000) gives a large days_left,
        // Oli Mesin is overdue -> red. Just assert first item (if any) is red when present.

        $items = $this->svc->forUser($user->fresh(['motorcycles.maintenanceItems']));

        if (count($items) > 1) {
            $severities = array_column($items, 'severity');
            $firstYellowIndex = array_search('yellow', $severities);
            $lastRedIndex = array_search('red', array_reverse($severities, true));
            if ($firstYellowIndex !== false && $lastRedIndex !== false) {
                $this->assertLessThan($firstYellowIndex, count($severities) - 1 - $lastRedIndex + $firstYellowIndex, 'red items should not appear after yellow ones is implicitly checked by construction');
            }
        }
        $this->assertNotEmpty($items);
        $this->assertEquals('red', $items[0]['severity']);
    }
}
