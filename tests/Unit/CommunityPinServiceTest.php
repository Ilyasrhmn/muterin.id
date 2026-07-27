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
        // return = [still_there_count, no_longer_count]; confirm_count net juga diupdate internal.
        $svc = new CommunityPinService;
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $pin = $this->pin($owner, -6.2, 106.8);

        $this->assertSame([1, 0], $svc->confirm($pin, $a, true));   // a=true          -> still 1, gone 0
        $this->assertSame(1, $pin->fresh()->confirm_count);
        $this->assertSame([1, 1], $svc->confirm($pin, $b, false));  // a=true, b=false -> still 1, gone 1
        $this->assertSame(0, $pin->fresh()->confirm_count);
        $this->assertSame([0, 2], $svc->confirm($pin, $a, false)); // a flips to false -> still 0, gone 2
        $this->assertSame(-2, $pin->fresh()->confirm_count);
    }

    public function test_confirm_same_value_twice_toggles_vote_off(): void
    {
        $svc = new CommunityPinService;
        $owner = User::factory()->create();
        $a = User::factory()->create();
        $pin = $this->pin($owner, -6.2, 106.8);

        $this->assertSame([1, 0], $svc->confirm($pin, $a, true));  // klik like pertama -> nyala
        $this->assertSame(1, $pin->confirmations()->where('user_id', $a->id)->count());

        $this->assertSame([0, 0], $svc->confirm($pin, $a, true));  // klik like lagi -> mati (toggle off)
        $this->assertSame(0, $pin->confirmations()->where('user_id', $a->id)->count());
        $this->assertSame(0, $pin->fresh()->confirm_count);

        $this->assertSame([0, 1], $svc->confirm($pin, $a, false)); // klik dislike -> nyala lagi (arah beda)
        $this->assertSame([0, 0], $svc->confirm($pin, $a, false)); // klik dislike lagi -> mati (toggle off)
        $this->assertSame(0, $pin->confirmations()->where('user_id', $a->id)->count());
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

    public function test_toggle_favorite_creates_then_removes(): void
    {
        $svc = new CommunityPinService;
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $pin = $this->pin($owner, -6.2, 106.8);

        $this->assertTrue($svc->toggleFavorite($pin, $user));
        $this->assertDatabaseHas('community_pin_favorites', ['community_pin_id' => $pin->id, 'user_id' => $user->id]);

        $this->assertFalse($svc->toggleFavorite($pin, $user));
        $this->assertDatabaseMissing('community_pin_favorites', ['community_pin_id' => $pin->id, 'user_id' => $user->id]);
    }

    public function test_liked_and_favorited_pin_ids(): void
    {
        $svc = new CommunityPinService;
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $liked = $this->pin($owner, -6.2, 106.8);
        $notLiked = $this->pin($owner, -6.3, 106.9);

        $svc->confirm($liked, $user, true);
        $svc->confirm($notLiked, $user, false);
        $svc->toggleFavorite($liked, $user);

        $this->assertSame([$liked->id], $svc->likedPinIds($user));
        $this->assertSame([$liked->id], $svc->favoritedPinIds($user));
    }
}
