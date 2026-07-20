<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_preview_route_returns_geometry_for_authenticated_user(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [[106.8, -6.2], [106.82, -6.22]]],
                    'properties' => ['summary' => ['distance' => 1000.0, 'duration' => 200.0]],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/map/route', [
            'waypoints' => [[-6.2, 106.8], [-6.22, 106.82]],
        ])->assertOk()->assertJson([
            'distance_km' => 1.0,
            'duration_minutes' => 3,
        ]);
    }

    public function test_preview_route_requires_at_least_two_waypoints(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/map/route', [
            'waypoints' => [[-6.2, 106.8]],
        ])->assertStatus(422);
    }

    public function test_preview_route_returns_422_when_routing_fails(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(['error' => 'no route'], 404),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/map/route', [
            'waypoints' => [[-6.2, 106.8], [-6.22, 106.82]],
        ])->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_preview_route_requires_authentication(): void
    {
        $this->postJson('/map/route', [
            'waypoints' => [[-6.2, 106.8], [-6.22, 106.82]],
        ])->assertStatus(401);
    }

    public function test_saving_route_plan_stores_geometry_distance_and_duration(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/map/plans', [
            'name' => 'Rute pagi',
            'points' => [[-6.2, 106.8], [-6.22, 106.82]],
            'route_geometry' => [[-6.2, 106.8], [-6.21, 106.81], [-6.22, 106.82]],
            'distance_km' => 3.5,
            'duration_minutes' => 12,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('route_plans', [
            'name' => 'Rute pagi', 'distance_km' => 3.5, 'duration_minutes' => 12,
        ]);
    }
}
