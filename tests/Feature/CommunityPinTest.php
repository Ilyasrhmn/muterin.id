<?php

namespace Tests\Feature;

use App\Models\CommunityPin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPinTest extends TestCase
{
    use RefreshDatabase;

    private function pin(User $u, array $attrs = []): CommunityPin
    {
        return $u->communityPins()->create(array_merge([
            'category' => 'sepi', 'lat' => -6.2, 'lng' => 106.8, 'title' => 'Jalan sepi',
            'time_context' => 'malam',
        ], $attrs));
    }

    public function test_visible_scope_hides_only_old_and_disproven_pins(): void
    {
        $u = User::factory()->create();
        $fresh = $this->pin($u, ['confirm_count' => -5]);           // baru, tetap tampil
        $oldButConfirmed = $this->pin($u, ['confirm_count' => 3]);  // tua tapi masih dipercaya
        // ponytail: forceFill, bukan update() -- created_at sengaja tak fillable di model.
        $oldButConfirmed->forceFill(['created_at' => now()->subDays(40)])->save();
        $oldDisproven = $this->pin($u, ['confirm_count' => -1]);    // tua + dibantah -> hilang
        $oldDisproven->forceFill(['created_at' => now()->subDays(40)])->save();

        $visibleIds = CommunityPin::visible()->pluck('id');

        $this->assertTrue($visibleIds->contains($fresh->id));
        $this->assertTrue($visibleIds->contains($oldButConfirmed->id));
        $this->assertFalse($visibleIds->contains($oldDisproven->id));
    }
}
