<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_landing_page(): void
    {
        $this->get('/')->assertOk()->assertSee('Amicta');
    }

    public function test_logged_in_user_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }
}
