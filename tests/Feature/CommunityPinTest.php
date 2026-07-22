<?php

namespace Tests\Feature;

use App\Models\CommunityPin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_pin_is_visible_to_other_users(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author)->postJson('/peta/komunitas', [
            'category' => 'rawan', 'lat' => -6.2, 'lng' => 106.8,
            'title' => 'Rawan begal', 'time_context' => 'malam',
        ])->assertCreated();

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->getJson('/peta/komunitas/data')
            ->assertOk()->assertJsonFragment(['title' => 'Rawan begal']);
    }

    public function test_photo_is_stored_on_public_disk(): void
    {
        Storage::fake('public');
        $author = User::factory()->create();

        $this->actingAs($author)->post('/peta/komunitas', [
            'category' => 'momen', 'lat' => -6.2, 'lng' => 106.8,
            'title' => 'Sunset', 'time_context' => 'kapanpun',
            // ponytail: png bukan jpg -- GD build di sandbox ini tanpa libjpeg (gd_info: JPEG Support => false).
            'photo' => UploadedFile::fake()->image('sunset.png'),
        ])->assertCreated();

        $pin = CommunityPin::first();
        $this->assertNotNull($pin->photo_path);
        Storage::disk('public')->assertExists($pin->photo_path);
    }

    public function test_anonymous_pin_hides_contributor_name(): void
    {
        $author = User::factory()->create(['name' => 'Budi']);
        $this->actingAs($author)->postJson('/peta/komunitas', [
            'category' => 'sepi', 'lat' => -6.2, 'lng' => 106.8,
            'title' => 'Sepi', 'time_context' => 'malam', 'is_anonymous' => true,
        ])->assertCreated();

        $this->actingAs($author)->getJson('/peta/komunitas/data')
            ->assertJsonFragment(['contributor' => null])
            ->assertJsonMissing(['contributor' => 'Budi']);
    }

    public function test_non_owner_cannot_delete_pin(): void
    {
        $author = User::factory()->create();
        $id = $this->actingAs($author)->postJson('/peta/komunitas', [
            'category' => 'sepi', 'lat' => -6.2, 'lng' => 106.8,
            'title' => 'Sepi', 'time_context' => 'malam',
        ])->json('id');

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->deleteJson("/peta/komunitas/{$id}")->assertStatus(403);
        $this->assertDatabaseHas('community_pins', ['id' => $id]);
    }

    public function test_confirm_returns_updated_count(): void
    {
        $author = User::factory()->create();
        $id = $this->actingAs($author)->postJson('/peta/komunitas', [
            'category' => 'sepi', 'lat' => -6.2, 'lng' => 106.8,
            'title' => 'Sepi', 'time_context' => 'malam',
        ])->json('id');

        $voter = User::factory()->create();
        $this->actingAs($voter)->postJson("/peta/komunitas/{$id}/confirm", ['still_there' => true])
            ->assertOk()->assertJson(['confirm_count' => 1]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/peta/komunitas', [
            'category' => 'sepi', 'lat' => -6.2, 'lng' => 106.8,
            'title' => 'X', 'time_context' => 'malam',
        ])->assertStatus(401);
    }
}
