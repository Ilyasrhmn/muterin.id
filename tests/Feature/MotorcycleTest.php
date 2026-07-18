<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorcycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_motorcycle_seeds_four_default_items(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id,
            'nickname' => 'Beat Merah',
            'initial_odometer_km' => 10000,
            'current_odometer_km' => 10000,
        ]);

        $this->assertCount(4, $motor->maintenanceItems);
        $this->assertEqualsCanonicalizing(
            ['Oli Mesin', 'Ban', 'Aki', 'Servis Rutin'],
            $motor->maintenanceItems->pluck('name')->all()
        );
        $this->assertEquals(10000, $motor->maintenanceItems->firstWhere('name', 'Oli Mesin')->last_service_odometer_km);
    }

    public function test_user_only_sees_own_motorcycles(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        Motorcycle::create(['user_id' => $me->id, 'nickname' => 'Punyaku']);
        Motorcycle::create(['user_id' => $other->id, 'nickname' => 'Punya Orang']);

        $res = $this->actingAs($me)->get(route('motorcycles.index'));
        $res->assertSee('Punyaku');
        $res->assertDontSee('Punya Orang');
    }

    public function test_activating_one_motorcycle_deactivates_others(): void
    {
        $user = User::factory()->create();
        $a = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'is_active' => true]);
        $b = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'B']);

        $this->actingAs($user)->post(route('motorcycles.activate', $b));

        $this->assertFalse($a->fresh()->is_active);
        $this->assertTrue($b->fresh()->is_active);
    }
}
