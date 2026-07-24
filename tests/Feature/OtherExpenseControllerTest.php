<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtherExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_other_expense(): void
    {
        $user = User::factory()->create();
        ExpenseCategory::ensureDefaultsFor($user);
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($user)->post(route('other-expenses.store'), [
            'motorcycle_id' => $motor->id,
            'category' => 'Asuransi',
            'amount' => 500000,
            'expense_date' => '2026-07-19',
            'note' => 'Premi tahunan',
        ])->assertRedirect();

        $this->assertDatabaseHas('other_expenses', [
            'motorcycle_id' => $motor->id, 'category' => 'Asuransi', 'amount' => 500000,
        ]);
    }

    public function test_cannot_record_expense_for_other_users_motorcycle(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        ExpenseCategory::ensureDefaultsFor($intruder);
        $motor = Motorcycle::create(['user_id' => $owner->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($intruder)->post(route('other-expenses.store'), [
            'motorcycle_id' => $motor->id, 'category' => 'Parkir', 'amount' => 5000, 'expense_date' => '2026-07-19',
        ])->assertForbidden();
    }

    public function test_category_not_owned_by_user_is_rejected(): void
    {
        $user = User::factory()->create();
        ExpenseCategory::ensureDefaultsFor($user);
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($user)->post(route('other-expenses.store'), [
            'motorcycle_id' => $motor->id,
            'category' => 'KategoriAsing',
            'amount' => 15000,
            'expense_date' => '2026-07-19',
        ])->assertSessionHasErrors('category');
    }

    public function test_description_shows_in_history_list(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $motor->otherExpenses()->create([
            'category' => 'Parkir', 'amount' => 3000,
            'expense_date' => '2026-07-19', 'note' => 'Parkir Mall Ambarukmo',
        ]);

        $this->actingAs($user)->get('/history')
            ->assertOk()->assertSee('Parkir Mall Ambarukmo');
    }

    public function test_can_delete_own_expense(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $expense = $motor->otherExpenses()->create([
            'category' => 'Parkir', 'amount' => 5000, 'expense_date' => '2026-07-19',
        ]);

        $this->actingAs($user)->delete(route('other-expenses.destroy', $expense))->assertRedirect();
        $this->assertDatabaseMissing('other_expenses', ['id' => $expense->id]);
    }
}
