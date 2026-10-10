<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_all_records_with_complete_summary(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'Beat', 'current_odometer_km' => 1000]);
        $item = $motor->maintenanceItems()->first();
        for ($i = 0; $i < 12; $i++) {
            $item->logs()->create(['cost' => 10000, 'serviced_at_odometer_km' => 1000, 'serviced_at' => now()->subDays($i)]);
        }
        $first = $this->actingAs($user)->get(route('history'))->assertOk();
        $first->assertViewHas('logs', fn ($logs) => $logs->count() === 12);
        $first->assertViewHas('totalCost', 120000);
        $second = $this->get(route('history', ['service_page' => 2]))->assertOk();
        $second->assertViewHas('logs', fn ($logs) => $logs->count() === 12);
        $second->assertViewHas('totalCost', 120000);
    }
    public function test_period_filters_service_and_other_expenses_and_totals(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-10 12:00:00'));
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'Beat', 'current_odometer_km' => 1000]);
        $item = $motor->maintenanceItems()->first();
        foreach ([0, 6, 7, 29, 30] as $days) {
            $item->logs()->create(['cost' => 10000, 'serviced_at_odometer_km' => 1000, 'serviced_at' => now()->subDays($days)]);
            \App\Models\OtherExpense::create(['motorcycle_id' => $motor->id, 'category' => 'Parkir', 'amount' => 1000, 'expense_date' => now()->subDays($days)]);
        }
        foreach (['7' => 2, '30' => 4, 'all' => 5] as $period => $count) {
            $this->actingAs($user)->get(route('history', ['period' => $period]))->assertOk()
                ->assertViewHas('logs', fn ($logs) => $logs->count() === $count)
                ->assertViewHas('otherExpenses', fn ($expenses) => $expenses->count() === $count)
                ->assertViewHas('totalCost', $count * 11000)
                ->assertViewHas('breakdown', fn ($breakdown) => $breakdown->sum() === $count * 11000);
        }
        $this->travelBack();
    }

}
