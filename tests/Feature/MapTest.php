<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_and_list_own_pin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/map/pins', [
            'category' => 'hazard', 'lat' => -6.2, 'lng' => 106.8, 'title' => 'Jalan rusak',
        ])->assertCreated();

        $this->actingAs($user)->getJson('/map/data')->assertJsonFragment(['title' => 'Jalan rusak']);
    }

    public function test_user_cannot_see_other_users_pins(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/map/pins', [
            'category' => 'moment', 'lat' => -6.3, 'lng' => 106.9, 'title' => 'Punya orang lain',
        ])->assertCreated();

        $this->actingAs($me)->getJson('/map/data')->assertJsonMissing(['title' => 'Punya orang lain']);
    }

    public function test_user_can_delete_own_pin(): void
    {
        $user = User::factory()->create();
        $pinId = $this->actingAs($user)->postJson('/map/pins', [
            'category' => 'quiet', 'lat' => -6.1, 'lng' => 106.7, 'title' => 'Sepi malam',
        ])->json('id');

        $this->actingAs($user)->deleteJson("/map/pins/{$pinId}")->assertOk();
        $this->assertDatabaseMissing('map_pins', ['id' => $pinId]);
    }

    public function test_saving_route_plan_requires_at_least_two_points(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/map/plans', [
            'name' => 'Rute pendek', 'points' => [[-6.2, 106.8]],
        ])->assertStatus(422);
    }
}
