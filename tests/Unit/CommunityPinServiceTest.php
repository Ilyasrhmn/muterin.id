<?php

namespace Tests\Unit;

use App\Models\CommunityPin;
use App\Models\User;
use App\Services\CommunityPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPinServiceTest extends TestCase
{
    use RefreshDatabase;

    private function pin(User $u, float $lat, float $lng): CommunityPin
    {
        return $u->communityPins()->create([
            'category' => 'sepi', 'lat' => $lat, 'lng' => $lng,
            'title' => 'Titik', 'time_context' => 'kapanpun',
        ]);
    }

    public function test_confirm_counts_one_vote_per_user_and_updates_count(): void
    {
        // count = (#still_there true) - (#false)
        $svc = new CommunityPinService;
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $pin = $this->pin($owner, -6.2, 106.8);

        $this->assertSame(1, $svc->confirm($pin, $a, true));   // a=true                 -> 1
        $this->assertSame(0, $svc->confirm($pin, $b, false));  // a=true, b=false        -> 0
        $this->assertSame(-2, $svc->confirm($pin, $a, false)); // a flips to false, b=false -> -2
    }

    public function test_confirm_replaces_existing_vote_not_duplicates(): void
    {
        $svc = new CommunityPinService;
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $pin = $this->pin($owner, -6.2, 106.8);

        $svc->confirm($pin, $a, true);
        $svc->confirm($pin, $a, false);

        $this->assertSame(1, $pin->confirmations()->count()); // bukan 2
        $this->assertSame(-1, $pin->fresh()->confirm_count);
    }

    public function test_near_route_includes_only_pins_within_threshold(): void
    {
        $svc = new CommunityPinService;
        $owner = User::factory()->create();
        $onRoute = $this->pin($owner, -6.2001, 106.8001); // ~15m dari titik rute
        $farAway = $this->pin($owner, -6.5, 107.2);        // jauh

        $geometry = [[-6.2, 106.8], [-6.21, 106.81], [-6.22, 106.82]];
        $near = $svc->nearRoute($geometry, 300);

        $this->assertTrue($near->pluck('id')->contains($onRoute->id));
        $this->assertFalse($near->pluck('id')->contains($farAway->id));
    }

    public function test_near_route_handles_empty_geometry(): void
    {
        $svc = new CommunityPinService;
        $this->assertCount(0, $svc->nearRoute([], 300));
    }
}
