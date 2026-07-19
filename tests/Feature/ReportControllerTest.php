<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_page_shows_total_cost_of_ownership(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 1000,
        ]);
        $motor->fuelLogs()->create([
            'filled_at' => '2026-07-01', 'odometer_km' => 500, 'liters' => 4, 'total_cost' => 60000,
        ]);
        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();
        $oli->logs()->create([
            'serviced_at_odometer_km' => 500, 'cost' => 45000, 'serviced_at' => '2026-07-05',
        ]);

        $response = $this->actingAs($user)->get(route('laporan'));

        $response->assertOk();
        // TCO = 60000 + 45000 = 105000
        $response->assertSee('105');
    }

    public function test_report_page_loads_with_no_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('laporan'))->assertOk();
    }
}
