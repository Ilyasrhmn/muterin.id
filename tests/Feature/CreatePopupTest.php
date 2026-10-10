<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_category_reopens_popup_with_previous_input(): void
    {
        $this->actingAs(User::factory()->create());
        $name = str_repeat('a', 51);

        $page = $this->followingRedirects()->from(route('expense-categories.index'))->post(route('expense-categories.store'), [
            '_form' => 'add-category', 'name' => $name,
        ]);

        $page->assertOk()
            ->assertSee('style="display: block;"', false)
            ->assertSee('value="'.$name.'"', false)
            ->assertSee('Tambah kategori');
    }

    public function test_invalid_fuel_reopens_popup_and_preserves_unchecked_full_tank(): void
    {
        $this->actingAs(User::factory()->create());

        $page = $this->followingRedirects()->from(route('bbm.index'))->post(route('bbm.store'), [
            '_form' => 'add-fuel', 'filled_at' => '2026-10-09',
            'liters' => '3.5', 'is_full_tank' => '0',
        ]);

        $page->assertOk()
            ->assertSee('style="display: block;"', false)
            ->assertSee('value="3.5"', false)
            ->assertDontSee('value="1" checked', false)
            ->assertSee('Tambah catatan BBM');
    }
}
