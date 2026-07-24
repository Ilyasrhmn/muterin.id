<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_seeds_defaults_and_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/kategori-biaya')
            ->assertOk()->assertSee('Parkir');

        $this->assertCount(5, $user->expenseCategories()->get());
    }

    public function test_user_can_add_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/kategori-biaya', ['name' => 'Tol'])
            ->assertRedirect();

        $this->assertTrue($user->expenseCategories()->where('name', 'Tol')->exists());
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->expenseCategories()->create(['name' => 'Tol']);

        $this->actingAs($user)->post('/kategori-biaya', ['name' => 'Tol'])
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_rename_own_category(): void
    {
        $user = User::factory()->create();
        $cat = $user->expenseCategories()->create(['name' => 'Tol']);

        $this->actingAs($user)->patch("/kategori-biaya/{$cat->id}", ['name' => 'Tol & Parkir'])
            ->assertRedirect();

        $this->assertEquals('Tol & Parkir', $cat->fresh()->name);
    }

    public function test_user_cannot_touch_other_users_category(): void
    {
        $owner = User::factory()->create();
        $cat = $owner->expenseCategories()->create(['name' => 'Rahasia']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->delete("/kategori-biaya/{$cat->id}")->assertForbidden();
        $this->assertDatabaseHas('expense_categories', ['id' => $cat->id]);
    }
}
