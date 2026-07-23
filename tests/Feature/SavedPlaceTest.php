<?php

namespace Tests\Feature;

use App\Models\PlaceList;
use App\Models\SavedPlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SavedPlaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_defaults_creates_three_lists_idempotently(): void
    {
        $user = User::factory()->create();

        PlaceList::ensureDefaultsFor($user);
        PlaceList::ensureDefaultsFor($user); // dipanggil dua kali

        $this->assertSame(3, $user->placeLists()->count());
        $this->assertSame(3, $user->placeLists()->where('is_default', true)->count());
        $this->assertTrue($user->placeLists()->where('name', 'Favorit')->exists());
    }

    public function test_saved_place_belongs_to_list(): void
    {
        $user = User::factory()->create();
        $list = $user->placeLists()->create(['name' => 'Test', 'icon' => 'fa-star', 'color' => '#F59E0B']);
        $place = $user->savedPlaces()->create([
            'place_list_id' => $list->id, 'lat' => -7.7, 'lng' => 110.4, 'title' => 'Kafe',
        ]);

        $this->assertSame($list->id, $place->list->id);
    }

    public function test_data_returns_lists_and_places_scoped_to_user(): void
    {
        $me = User::factory()->create();
        PlaceList::ensureDefaultsFor($me);
        $favorit = $me->placeLists()->where('name', 'Favorit')->first();
        $me->savedPlaces()->create(['place_list_id' => $favorit->id, 'lat' => -7.7, 'lng' => 110.4, 'title' => 'Punyaku']);

        $other = User::factory()->create();
        PlaceList::ensureDefaultsFor($other);
        $otherFav = $other->placeLists()->where('name', 'Favorit')->first();
        $other->savedPlaces()->create(['place_list_id' => $otherFav->id, 'lat' => -6.2, 'lng' => 106.8, 'title' => 'Punya orang']);

        $this->actingAs($me)->getJson('/peta/titik/data')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Punyaku'])
            ->assertJsonMissing(['title' => 'Punya orang']);
    }

    public function test_create_custom_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/peta/titik/lists', [
            'name' => 'Kuliner', 'icon' => 'fa-utensils', 'color' => '#DC2626',
        ])->assertCreated();

        $this->assertDatabaseHas('place_lists', ['name' => 'Kuliner', 'is_default' => false]);
    }

    public function test_list_rejects_icon_outside_palette_and_bad_color(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/peta/titik/lists', [
            'name' => 'X', 'icon' => 'fa-skull', 'color' => '#DC2626',
        ])->assertStatus(422);
        $this->actingAs($user)->postJson('/peta/titik/lists', [
            'name' => 'X', 'icon' => 'fa-star', 'color' => 'red',
        ])->assertStatus(422);
    }

    public function test_default_list_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        PlaceList::ensureDefaultsFor($user);
        $favorit = $user->placeLists()->where('name', 'Favorit')->first();

        $this->actingAs($user)->deleteJson("/peta/titik/lists/{$favorit->id}")->assertStatus(422);
        $this->assertDatabaseHas('place_lists', ['id' => $favorit->id]);
    }

    public function test_deleting_custom_list_cascades_places(): void
    {
        $user = User::factory()->create();
        $list = $user->placeLists()->create(['name' => 'Sementara', 'icon' => 'fa-star', 'color' => '#F59E0B']);
        $place = $user->savedPlaces()->create(['place_list_id' => $list->id, 'lat' => -7.7, 'lng' => 110.4, 'title' => 'X']);

        $this->actingAs($user)->deleteJson("/peta/titik/lists/{$list->id}")->assertOk();
        $this->assertDatabaseMissing('saved_places', ['id' => $place->id]);
    }

    public function test_store_place_with_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        PlaceList::ensureDefaultsFor($user);
        $favorit = $user->placeLists()->where('name', 'Favorit')->first();

        $this->actingAs($user)->post('/peta/titik', [
            'place_list_id' => $favorit->id, 'lat' => -7.7, 'lng' => 110.4,
            'title' => 'Kafe', 'photo' => UploadedFile::fake()->image('k.png'),
        ])->assertCreated();

        $place = SavedPlace::first();
        $this->assertNotNull($place->photo_path);
        Storage::disk('public')->assertExists($place->photo_path);
    }

    public function test_cannot_save_to_another_users_list(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $otherList = $other->placeLists()->create(['name' => 'X', 'icon' => 'fa-star', 'color' => '#F59E0B']);

        $this->actingAs($me)->postJson('/peta/titik', [
            'place_list_id' => $otherList->id, 'lat' => -7.7, 'lng' => 110.4, 'title' => 'Nyelinap',
        ])->assertStatus(403);
    }

    public function test_non_owner_cannot_delete_place(): void
    {
        $owner = User::factory()->create();
        $list = $owner->placeLists()->create(['name' => 'X', 'icon' => 'fa-star', 'color' => '#F59E0B']);
        $place = $owner->savedPlaces()->create(['place_list_id' => $list->id, 'lat' => -7.7, 'lng' => 110.4, 'title' => 'X']);

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->deleteJson("/peta/titik/{$place->id}")->assertStatus(403);
        $this->assertDatabaseHas('saved_places', ['id' => $place->id]);
    }

    public function test_update_place_moves_list_and_changes_title(): void
    {
        $user = User::factory()->create();
        $a = $user->placeLists()->create(['name' => 'A', 'icon' => 'fa-star', 'color' => '#F59E0B']);
        $b = $user->placeLists()->create(['name' => 'B', 'icon' => 'fa-flag', 'color' => '#0EA5E9']);
        $place = $user->savedPlaces()->create(['place_list_id' => $a->id, 'lat' => -7.7, 'lng' => 110.4, 'title' => 'Lama']);

        $this->actingAs($user)->patchJson("/peta/titik/{$place->id}", [
            'place_list_id' => $b->id, 'title' => 'Baru',
        ])->assertOk();

        $this->assertDatabaseHas('saved_places', ['id' => $place->id, 'place_list_id' => $b->id, 'title' => 'Baru']);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/peta/titik/data')->assertStatus(401);
    }
}
