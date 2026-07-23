# Titik Saya → Tempat Tersimpan + List Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rework "Titik Saya" jadi tempat tersimpan pribadi ala Google Maps: simpan tempat ke dalam list (bawaan + custom, dengan icon & warna pilihan), tiap tempat bisa foto + deskripsi. Pensiunkan sistem pin berkategori lama.

**Architecture:** Halaman `/peta/titik` di-rework dilayani `SavedPlaceController` baru + dua tabel (`place_lists`, `saved_places`). Panel kiri persisten (manajer list + daftar tempat) + peta kanan. Sistem `map_pins` lama dimigrasikan lalu dihapus. Marker pakai `L.divIcon` Font Awesome per list (teknik sama dengan Peta Komunitas).

**Tech Stack:** Laravel 13, PHP 8.4, Blade + Leaflet (CDN) + Tailwind + Font Awesome, SQLite (dev), disk lokal untuk foto.

## Global Constraints

- Semua privat, owner-scoped ke `auth()->id()`. Satu tempat = satu list (FK `place_list_id`, bukan pivot).
- Foto → `storage/app/public/places` via disk `public` + `storage:link`.
- Tidak ada `alert/confirm/prompt` native → `window.MuterinDialog`. Form tambah/edit pakai panel/popup inline (ada upload foto).
- Marker `L.divIcon` Font Awesome pakai icon+warna list; peta auto-`fitTo` ke tempat saat load (hindari bug marker off-screen).
- Palet icon (whitelist, validasi `in:`): `fa-star, fa-flag, fa-heart, fa-bookmark, fa-wrench, fa-mug-hot, fa-house, fa-camera, fa-road, fa-mountain, fa-utensils, fa-gas-pump, fa-location-dot`. Warna validasi hex `regex:/^#[0-9A-Fa-f]{6}$/`.
- List bawaan (`is_default=true`): Favorit (`fa-star`/`#F59E0B`), Mau ke sana (`fa-flag`/`#0EA5E9`), Bengkel Langganan (`fa-wrench`/`#0F766E`). Dibuat lazily via `PlaceList::ensureDefaultsFor()`. List bawaan tak bisa dihapus (422).
- Cache-bust `public/js/*.js` baru: `?v={{ filemtime(...) }}`.
- Test PHPUnit class-based, `RefreshDatabase`, `User::factory()` (lihat `tests/Feature/MapTest.php`). String UI Bahasa Indonesia.

## File Structure

- Create: `database/migrations/..._create_place_lists_table.php`, `..._create_saved_places_table.php`
- Create: `app/Models/PlaceList.php`, `app/Models/SavedPlace.php`
- Modify: `app/Models/User.php` (tambah `placeLists`/`savedPlaces`, hapus `mapPins`)
- Create: `app/Http/Controllers/SavedPlaceController.php`
- Modify: `routes/web.php`, `resources/views/layouts/navigation.blade.php`
- Create: `resources/views/map/saved.blade.php`, `public/js/map-saved.js`
- Create: `database/migrations/..._migrate_map_pins_to_saved_places.php` (data + drop)
- Delete: `app/Models/MapPin.php`, `resources/views/map/pins.blade.php`, `public/js/map-pins.js`
- Modify: `app/Http/Controllers/MapController.php` (hapus pinsPage/storePin/destroyPin + key `pins`), `tests/Feature/MapTest.php` (hapus 3 test pin)
- Test: `tests/Feature/SavedPlaceTest.php`

---

### Task 1: Migrasi, model, relasi

**Files:**
- Create: `database/migrations/2026_07_22_100001_create_place_lists_table.php`
- Create: `database/migrations/2026_07_22_100002_create_saved_places_table.php`
- Create: `app/Models/PlaceList.php`, `app/Models/SavedPlace.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/SavedPlaceTest.php` (baru, hanya test model di task ini)

**Interfaces:**
- Produces: `PlaceList` (const `ICONS`, `ensureDefaultsFor(User)`, relasi `user`/`places`), `SavedPlace` (relasi `user`/`list`), `User::placeLists()`/`savedPlaces()`.

- [ ] **Step 1: Migrasi `place_lists`**

`database/migrations/2026_07_22_100001_create_place_lists_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->default('fa-bookmark');
            $table->string('color', 9)->default('#0F766E');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_lists');
    }
};
```

- [ ] **Step 2: Migrasi `saved_places`**

`database/migrations/2026_07_22_100002_create_saved_places_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_list_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_places');
    }
};
```

- [ ] **Step 3: Model `SavedPlace`**

`app/Models/SavedPlace.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPlace extends Model
{
    protected $fillable = ['user_id', 'place_list_id', 'lat', 'lng', 'title', 'description', 'photo_path'];

    protected $casts = ['lat' => 'float', 'lng' => 'float'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(PlaceList::class, 'place_list_id');
    }
}
```

- [ ] **Step 4: Model `PlaceList`**

`app/Models/PlaceList.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaceList extends Model
{
    public const ICONS = [
        'fa-star', 'fa-flag', 'fa-heart', 'fa-bookmark', 'fa-wrench', 'fa-mug-hot',
        'fa-house', 'fa-camera', 'fa-road', 'fa-mountain', 'fa-utensils', 'fa-gas-pump', 'fa-location-dot',
    ];

    protected $fillable = ['user_id', 'name', 'icon', 'color', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(SavedPlace::class);
    }

    // Buat list bawaan untuk user kalau belum ada. Idempotent.
    // ponytail: daftar & atribut default = tuning-knob.
    public static function ensureDefaultsFor(User $user): void
    {
        $defaults = [
            ['name' => 'Favorit', 'icon' => 'fa-star', 'color' => '#F59E0B'],
            ['name' => 'Mau ke sana', 'icon' => 'fa-flag', 'color' => '#0EA5E9'],
            ['name' => 'Bengkel Langganan', 'icon' => 'fa-wrench', 'color' => '#0F766E'],
        ];
        foreach ($defaults as $d) {
            $user->placeLists()->firstOrCreate(
                ['name' => $d['name'], 'is_default' => true],
                ['icon' => $d['icon'], 'color' => $d['color']],
            );
        }
    }
}
```

- [ ] **Step 5: Relasi di `User`**

Di `app/Models/User.php`, tambahkan dua method (di dekat `routePlans()`):

```php
public function placeLists(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(PlaceList::class);
}

public function savedPlaces(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(SavedPlace::class);
}
```

> Catatan: JANGAN hapus `mapPins()` di task ini  itu dilakukan di Task 4 setelah migrasi data. Halaman lama masih hidup sampai Task 3.

- [ ] **Step 6: Test model**

`tests/Feature/SavedPlaceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PlaceList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
```

- [ ] **Step 7: Jalankan test**

Run: `php artisan test --filter=SavedPlaceTest`
Expected: PASS (2 test).

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models tests/Feature/SavedPlaceTest.php
git commit -m "feat: place_lists + saved_places schema, models, default-list helper"
```

---

### Task 2: SavedPlaceController + endpoint + test

**Files:**
- Create: `app/Http/Controllers/SavedPlaceController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SavedPlaceTest.php` (tambah test endpoint)

**Interfaces:**
- Consumes: `PlaceList`, `SavedPlace` (Task 1).
- Produces: endpoint JSON dipakai `map-saved.js` (Task 3). Payload `data` = `{ lists: [...], places: [...] }` (bentuk di Step 1). `store`/`update` kembalikan place JSON (bentuk `presentPlace`).

> Catatan: Task ini TIDAK menyentuh route `GET /peta/titik` (masih halaman lama) maupun nav. Semua endpoint baru pakai sub-path lain (`/peta/titik/data`, `/peta/titik/lists`, dst) + `POST/PATCH/DELETE /peta/titik/{place}`. Peralihan halaman ada di Task 3. Test hanya memukul endpoint JSON (bukan render view), jadi view belum perlu ada.

- [ ] **Step 1: Controller**

`app/Http/Controllers/SavedPlaceController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\PlaceList;
use App\Models\SavedPlace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SavedPlaceController extends Controller
{
    public function index()
    {
        PlaceList::ensureDefaultsFor(auth()->user());

        return view('map.saved');
    }

    public function data()
    {
        $userId = auth()->id();
        PlaceList::ensureDefaultsFor(auth()->user());

        $lists = PlaceList::where('user_id', $userId)->withCount('places')->orderBy('id')->get();
        $places = SavedPlace::where('user_id', $userId)->with('list')->latest()->get();

        return response()->json([
            'lists' => $lists->map(fn (PlaceList $l) => [
                'id' => $l->id, 'name' => $l->name, 'icon' => $l->icon,
                'color' => $l->color, 'is_default' => $l->is_default, 'place_count' => $l->places_count,
            ]),
            'places' => $places->map(fn (SavedPlace $p) => $this->presentPlace($p)),
        ]);
    }

    public function storeList(Request $request)
    {
        $data = $this->validateList($request);
        $list = $request->user()->placeLists()->create($data + ['is_default' => false]);

        return response()->json($list, 201);
    }

    public function updateList(Request $request, PlaceList $list)
    {
        abort_unless($list->user_id === auth()->id(), 403);
        $list->update($this->validateList($request));

        return response()->json($list);
    }

    public function destroyList(PlaceList $list)
    {
        abort_unless($list->user_id === auth()->id(), 403);
        if ($list->is_default) {
            return response()->json(['error' => 'List bawaan tidak bisa dihapus.'], 422);
        }
        // Hapus foto tiap tempat sebelum cascade DB menghapus barisnya.
        foreach ($list->places()->whereNotNull('photo_path')->pluck('photo_path') as $path) {
            Storage::disk('public')->delete($path);
        }
        $list->delete(); // cascade delete saved_places

        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'place_list_id' => 'required|integer',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:4096',
        ]);
        $this->assertOwnsList($data['place_list_id']);

        $data['photo_path'] = $request->file('photo')?->store('places', 'public');
        unset($data['photo']);

        $place = $request->user()->savedPlaces()->create($data);

        return response()->json($this->presentPlace($place->load('list')), 201);
    }

    public function update(Request $request, SavedPlace $place)
    {
        abort_unless($place->user_id === auth()->id(), 403);
        $data = $request->validate([
            'place_list_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);
        $this->assertOwnsList($data['place_list_id']);
        $place->update($data);

        return response()->json($this->presentPlace($place->load('list')));
    }

    public function destroy(SavedPlace $place)
    {
        abort_unless($place->user_id === auth()->id(), 403);
        if ($place->photo_path) {
            Storage::disk('public')->delete($place->photo_path);
        }
        $place->delete();

        return response()->json(['ok' => true]);
    }

    private function validateList(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'icon' => ['required', Rule::in(PlaceList::ICONS)],
            'color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);
    }

    private function assertOwnsList(int $listId): void
    {
        abort_unless(
            PlaceList::where('id', $listId)->where('user_id', auth()->id())->exists(),
            403,
        );
    }

    private function presentPlace(SavedPlace $p): array
    {
        return [
            'id' => $p->id,
            'place_list_id' => $p->place_list_id,
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'title' => $p->title,
            'description' => $p->description,
            'photo_url' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
            'list_name' => $p->list?->name,
            'list_icon' => $p->list?->icon,
            'list_color' => $p->list?->color,
        ];
    }
}
```

- [ ] **Step 2: Route**

Di `routes/web.php`, dalam grup `auth`, tambahkan import `use App\Http\Controllers\SavedPlaceController;` di atas, lalu tambahkan blok ini setelah route `map.plans.destroy` (JANGAN sentuh route `map.pins` lama dulu):

```php
    // Tempat Tersimpan (privat, saved-places ala Google Maps)
    Route::get('peta/titik/data', [SavedPlaceController::class, 'data'])->name('map.saved.data');
    Route::post('peta/titik/lists', [SavedPlaceController::class, 'storeList'])->name('map.saved.lists.store');
    Route::patch('peta/titik/lists/{list}', [SavedPlaceController::class, 'updateList'])->name('map.saved.lists.update');
    Route::delete('peta/titik/lists/{list}', [SavedPlaceController::class, 'destroyList'])->name('map.saved.lists.destroy');
    Route::post('peta/titik', [SavedPlaceController::class, 'store'])->name('map.saved.store');
    Route::patch('peta/titik/{place}', [SavedPlaceController::class, 'update'])->name('map.saved.update');
    Route::delete('peta/titik/{place}', [SavedPlaceController::class, 'destroy'])->name('map.saved.destroy');
```

> Route model binding: `{list}` mengikat ke `PlaceList`, `{place}` mengikat ke `SavedPlace`. Karena Laravel binding pakai nama variabel, pastikan parameter method bernama `$list`/`$place` (sudah, di controller). Path `peta/titik/lists` statis, didefinisikan sebelum `peta/titik/{place}`  aman karena beda segmen.

- [ ] **Step 3: Test endpoint**

Tambahkan ke `tests/Feature/SavedPlaceTest.php`. Tambahkan `use` di atas: `use App\Models\SavedPlace;`, `use Illuminate\Http\UploadedFile;`, `use Illuminate\Support\Facades\Storage;`.

```php
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
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=SavedPlaceTest`
Expected: PASS (semua, termasuk 2 test model dari Task 1).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/SavedPlaceController.php routes/web.php tests/Feature/SavedPlaceTest.php
git commit -m "feat: saved-place & list endpoints (data/list CRUD/place CRUD) owner-scoped"
```

---

### Task 3: Halaman baru (blade + JS) + peralihan `/peta/titik`

**Files:**
- Create: `resources/views/map/saved.blade.php`
- Create: `public/js/map-saved.js`
- Modify: `routes/web.php` (ganti `GET peta/titik` ke controller baru)
- Modify: `resources/views/layouts/navigation.blade.php` (link ke `map.saved`)

**Interfaces:**
- Consumes: endpoint Task 2, `window.MuterinMap`, `window.MuterinDialog`.
- Produces: halaman fungsional.

- [ ] **Step 1: Pastikan symlink storage**

Run: `php artisan storage:link` (idempotent).

- [ ] **Step 2: Ganti route `GET peta/titik`**

Di `routes/web.php`, cari baris:

```php
    Route::get('peta/titik', [MapController::class, 'pinsPage'])->name('map.pins');
```

Ganti jadi:

```php
    Route::get('peta/titik', [SavedPlaceController::class, 'index'])->name('map.saved');
```

(Biarkan `map.pins.store`/`map.pins.destroy` untuk sekarang  dihapus di Task 4.)

- [ ] **Step 3: Link navigasi**

Di `resources/views/layouts/navigation.blade.php`, ganti baris `map.pins` jadi:

```php
        ['route' => 'map.saved', 'pattern' => 'map.saved', 'label' => 'Titik Saya', 'icon' => 'map-pin'],
```

- [ ] **Step 4: Blade view**

`resources/views/map/saved.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">Titik Saya</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <x-ui.hero badge="Tempat tersimpan" title="Titik Saya"
                    subtitle="Simpan tempat ke dalam list-mu sendiri  favorit, mau ke sana, bengkel langganan, atau list bikinanmu. Klik di peta untuk menyimpan." />

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            {{-- LEFT: list manager + daftar tempat --}}
            <div class="space-y-6">
                {{-- Manajer list --}}
                <div class="bg-surface border border-border rounded-2xl p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-heading font-bold text-foreground text-sm">List Saya</h3>
                        <button id="btn-new-list" type="button" class="text-xs font-semibold text-primary hover:underline">+ Buat List</button>
                    </div>
                    <div id="list-manager" class="space-y-1"></div>

                    {{-- Form buat/edit list (tersembunyi) --}}
                    <div id="list-form" class="hidden border-t border-border pt-3 space-y-2">
                        <input id="lf-name" type="text" maxlength="255" placeholder="Nama list"
                               class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <div>
                            <p class="text-xs font-semibold text-muted-fg mb-1">Icon</p>
                            <div id="lf-icons" class="flex flex-wrap gap-1.5"></div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-muted-fg mb-1">Warna</p>
                            <div id="lf-colors" class="flex flex-wrap gap-1.5"></div>
                        </div>
                        <p id="lf-error" class="hidden text-xs text-accent"></p>
                        <div class="flex gap-2">
                            <x-ui.button id="lf-cancel" variant="outline" size="sm" type="button" class="flex-1 justify-center">Batal</x-ui.button>
                            <x-ui.button id="lf-save" variant="primary" size="sm" type="button" class="flex-1 justify-center">Simpan</x-ui.button>
                        </div>
                    </div>
                </div>

                {{-- Form tambah tempat (tersembunyi sampai lokasi dipilih) --}}
                <div id="place-form" class="hidden bg-surface border border-primary/30 rounded-2xl p-5 space-y-3">
                    <h3 class="font-heading font-bold text-foreground text-sm">Simpan Tempat</h3>
                    <p id="pf-coords" class="text-xs text-muted-fg"></p>
                    <input id="pf-title" type="text" maxlength="255" placeholder="Nama tempat"
                           class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <textarea id="pf-desc" rows="2" maxlength="2000" placeholder="Catatan (opsional)"
                              class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Simpan ke list</span>
                        <select id="pf-list" class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm"></select>
                    </label>
                    <label id="pf-photo-wrap" class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Foto (opsional)</span>
                        <input id="pf-photo" type="file" accept="image/*"
                               class="w-full text-xs text-muted-fg file:mr-3 file:rounded-lg file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                    </label>
                    <p id="pf-error" class="hidden text-xs text-accent"></p>
                    <div class="flex gap-2">
                        <x-ui.button id="pf-cancel" variant="outline" size="sm" type="button" class="flex-1 justify-center">Batal</x-ui.button>
                        <x-ui.button id="pf-save" variant="primary" size="sm" type="button" class="flex-1 justify-center">Simpan</x-ui.button>
                    </div>
                </div>

                {{-- Daftar tempat --}}
                <div class="bg-surface border border-border rounded-2xl overflow-hidden flex flex-col">
                    <div class="p-5 border-b border-border bg-muted/40">
                        <h3 class="font-heading font-bold text-foreground text-sm">Tempat Tersimpan</h3>
                    </div>
                    <div id="place-list" class="p-3 space-y-1 overflow-y-auto" style="max-height: 40vh"></div>
                </div>
            </div>

            {{-- RIGHT: map --}}
            <div class="lg:col-span-2">
                <div class="bg-surface border border-border rounded-2xl p-3 mb-3 flex items-center gap-3">
                    <button id="btn-my-location" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-primary/10 text-primary hover:bg-primary/20 transition">
                        <i class="fas fa-location-crosshairs"></i> Lokasi Saya
                    </button>
                    <p class="text-xs text-muted-fg">atau klik di mana saja di peta untuk menyimpan tempat.</p>
                </div>
                <div class="rounded-2xl overflow-hidden border border-border">
                    <div id="map" style="height: 68vh"></div>
                </div>
            </div>
        </div>
    </div>

    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}?v={{ filemtime(public_path('js/map-common.js')) }}"></script>
    <script src="{{ asset('js/map-saved.js') }}?v={{ filemtime(public_path('js/map-saved.js')) }}"></script>
</x-app-layout>
```

- [ ] **Step 5: JavaScript**

`public/js/map-saved.js`:

```javascript
(function () {
  const map = window.MuterinMap.init('map');
  const token = window.MuterinMap.token();
  const $ = (id) => document.getElementById(id);

  const ICONS = ['fa-star', 'fa-flag', 'fa-heart', 'fa-bookmark', 'fa-wrench', 'fa-mug-hot',
    'fa-house', 'fa-camera', 'fa-road', 'fa-mountain', 'fa-utensils', 'fa-gas-pump', 'fa-location-dot'];
  const COLORS = ['#F59E0B', '#0EA5E9', '#0F766E', '#DC2626', '#6366F1', '#DB2777', '#65A30D', '#78716C'];

  let lists = [];
  let places = [];
  let markers = new Map();
  let picked = null;
  let placeFormMode = 'new'; // 'new' | id tempat yang diedit
  let filter = '';            // '' = semua, atau id list
  let hasFitted = false;
  let listFormMode = null;    // 'new' | id list yang diedit
  let lfIcon = ICONS[0];
  let lfColor = COLORS[0];

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }
  function listById(id) { return lists.find((l) => l.id === id); }

  function placeIcon(color, icon) {
    return L.divIcon({
      html: `<div class="flex items-center justify-center w-9 h-9 rounded-full shadow-lg border-2 bg-white" style="border-color:${color}">
               <i class="fas ${icon}" style="color:${color}"></i></div>`,
      className: 'custom-pin-marker',
      iconSize: [36, 36], iconAnchor: [18, 36], popupAnchor: [0, -36],
    });
  }

  function tooltipHtml(p) {
    const photo = p.photo_url
      ? `<img src="${esc(p.photo_url)}" alt="" style="width:100%;height:80px;object-fit:cover;border-radius:6px;margin-bottom:6px">` : '';
    return `<div style="min-width:150px;max-width:190px">${photo}
      <span style="display:inline-block;font-size:9px;font-weight:700;color:#fff;background:${p.list_color || '#64748B'};padding:1px 7px;border-radius:999px">${esc(p.list_name || '')}</span>
      <p style="font-weight:700;font-size:13px;color:#0F172A;margin:4px 0 0">${esc(p.title)}</p></div>`;
  }

  // --- Popup penuh dengan edit/hapus ---
  function openPlacePopup(p, latlng) {
    const el = document.createElement('div');
    const photo = p.photo_url
      ? `<img src="${esc(p.photo_url)}" alt="" style="width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:8px">` : '';
    el.innerHTML = `<div style="min-width:210px;max-width:230px">${photo}
      <span style="display:inline-block;font-size:10px;font-weight:700;color:#fff;background:${p.list_color || '#64748B'};padding:2px 8px;border-radius:999px">${esc(p.list_name || '')}</span>
      <p style="font-weight:700;font-size:14px;color:#0F172A;margin:6px 0 2px">${esc(p.title)}</p>
      ${p.description ? `<p style="font-size:12px;color:#475569;margin:0 0 6px">${esc(p.description)}</p>` : ''}
      <div style="display:flex;gap:6px;margin-top:4px">
        <button data-act="edit" style="flex:1;font-size:11px;font-weight:600;padding:6px;border-radius:8px;border:0;cursor:pointer;background:#F1F5F9;color:#0F172A">Edit</button>
        <button data-act="del" style="flex:1;font-size:11px;font-weight:600;padding:6px;border-radius:8px;border:0;cursor:pointer;background:#FEF2F2;color:#B91C1C">Hapus</button>
      </div></div>`;
    el.querySelector('[data-act="edit"]').onclick = () => { map.closePopup(); editPlace(p); };
    el.querySelector('[data-act="del"]').onclick = async () => {
      const ok = await window.MuterinDialog.confirm('Hapus tempat ini?', { danger: true, confirmText: 'Hapus' });
      if (!ok) return;
      fetch(`/peta/titik/${p.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } })
        .then(() => { map.closePopup(); refresh(); });
    };
    L.popup({ maxWidth: 250 }).setLatLng(latlng).setContent(el).openOn(map);
  }

  // --- Render list manager ---
  function renderLists() {
    const box = $('list-manager');
    box.innerHTML = '';
    const allBtn = document.createElement('button');
    allBtn.type = 'button';
    allBtn.className = 'w-full text-left px-3 py-2 rounded-lg text-sm transition ' + (filter === '' ? 'bg-primary/10 text-primary font-semibold' : 'hover:bg-muted/60 text-foreground');
    allBtn.textContent = `Semua (${places.length})`;
    allBtn.onclick = () => { filter = ''; render(); };
    box.appendChild(allBtn);

    lists.forEach((l) => {
      const row = document.createElement('div');
      row.className = 'flex items-center gap-2 px-2 py-1.5 rounded-lg ' + (filter === l.id ? 'bg-primary/10' : 'hover:bg-muted/60');
      const pick = document.createElement('button');
      pick.type = 'button';
      pick.className = 'flex items-center gap-2 flex-1 min-w-0 text-left text-sm';
      pick.innerHTML = `<span class="flex items-center justify-center w-6 h-6 rounded-full shrink-0" style="background:${l.color}22"><i class="fas ${esc(l.icon)}" style="color:${l.color};font-size:11px"></i></span>
        <span class="truncate ${filter === l.id ? 'font-semibold text-primary' : 'text-foreground'}">${esc(l.name)}</span>
        <span class="text-[11px] text-muted-fg shrink-0">${l.place_count}</span>`;
      pick.onclick = () => { filter = l.id; render(); };
      row.appendChild(pick);

      if (!l.is_default) {
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'text-muted-fg hover:text-primary shrink-0 text-xs px-1';
        edit.innerHTML = '<i class="fas fa-pen"></i>';
        edit.onclick = () => openListForm(l);
        row.appendChild(edit);

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'text-muted-fg hover:text-accent shrink-0 text-xs px-1';
        del.innerHTML = '<i class="fas fa-trash"></i>';
        del.onclick = async () => {
          const ok = await window.MuterinDialog.confirm(`Hapus list "${l.name}" beserta ${l.place_count} tempat di dalamnya?`, { danger: true, confirmText: 'Hapus' });
          if (!ok) return;
          fetch(`/peta/titik/lists/${l.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } })
            .then(() => { if (filter === l.id) filter = ''; refresh(); });
        };
        row.appendChild(del);
      }
      box.appendChild(row);
    });
  }

  // --- Render markers + daftar tempat ---
  function render() {
    renderLists();
    const shown = places.filter((p) => filter === '' || p.place_list_id === filter);
    const keep = new Set(shown.map((p) => p.id));

    markers.forEach((layer, id) => { if (!keep.has(id)) { layer.remove(); markers.delete(id); } });
    shown.forEach((p) => {
      if (markers.has(p.id)) return;
      const layer = L.marker([p.lat, p.lng], { icon: placeIcon(p.list_color || '#64748B', p.list_icon || 'fa-location-dot') }).addTo(map);
      layer.bindTooltip(tooltipHtml(p), { direction: 'top', offset: [0, -30], className: 'community-pin-tooltip' });
      layer.on('click', () => openPlacePopup(p, [p.lat, p.lng]));
      markers.set(p.id, layer);
    });

    if (!hasFitted && shown.length) { hasFitted = true; window.MuterinMap.fitTo(map, shown.map((p) => [p.lat, p.lng])); }

    const list = $('place-list');
    list.innerHTML = '';
    if (!shown.length) { list.innerHTML = '<div class="p-6 text-center text-sm text-muted-fg">Belum ada tempat.</div>'; return; }
    shown.forEach((p) => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'block w-full text-left p-3 rounded-xl hover:bg-muted/60 transition';
      row.innerHTML = `<div class="flex items-center gap-2"><i class="fas ${esc(p.list_icon || 'fa-location-dot')}" style="color:${p.list_color || '#64748B'};font-size:12px"></i>
        <p class="font-bold text-sm text-foreground truncate">${esc(p.title)}</p></div>
        <p class="text-[11px] text-muted-fg mt-0.5 ml-5">${esc(p.list_name || '')}</p>`;
      row.onclick = () => { map.setView([p.lat, p.lng], 15); openPlacePopup(p, [p.lat, p.lng]); };
      list.appendChild(row);
    });
  }

  function refresh() {
    fetch('/peta/titik/data', { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((b) => { lists = b.lists || []; places = b.places || []; render(); })
      .catch(() => {});
  }

  // --- List form (buat/edit) ---
  function paintPickers() {
    $('lf-icons').innerHTML = '';
    ICONS.forEach((ic) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'w-8 h-8 rounded-lg border flex items-center justify-center ' + (ic === lfIcon ? 'border-primary bg-primary/10' : 'border-border');
      b.innerHTML = `<i class="fas ${ic}"></i>`;
      b.onclick = () => { lfIcon = ic; paintPickers(); };
      $('lf-icons').appendChild(b);
    });
    $('lf-colors').innerHTML = '';
    COLORS.forEach((c) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'w-8 h-8 rounded-full border-2 ' + (c === lfColor ? 'border-foreground' : 'border-transparent');
      b.style.background = c;
      b.onclick = () => { lfColor = c; paintPickers(); };
      $('lf-colors').appendChild(b);
    });
  }

  function openListForm(list) {
    listFormMode = list ? list.id : 'new';
    lfIcon = list ? list.icon : ICONS[0];
    lfColor = list ? list.color : COLORS[0];
    $('lf-name').value = list ? list.name : '';
    $('lf-error').classList.add('hidden');
    paintPickers();
    $('list-form').classList.remove('hidden');
  }

  $('btn-new-list').onclick = () => openListForm(null);
  $('lf-cancel').onclick = () => { listFormMode = null; $('list-form').classList.add('hidden'); };
  $('lf-save').onclick = () => {
    const name = $('lf-name').value.trim();
    if (!name) { showErr('lf-error', 'Nama list wajib diisi.'); return; }
    const isNew = listFormMode === 'new';
    fetch(isNew ? '/peta/titik/lists' : `/peta/titik/lists/${listFormMode}`, {
      method: isNew ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ name, icon: lfIcon, color: lfColor }),
    }).then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (!ok) { showErr('lf-error', firstError(body) || 'Gagal menyimpan list.'); return; }
        listFormMode = null; $('list-form').classList.add('hidden'); refresh();
      });
  };

  // --- Place form (tambah / edit) ---
  function pickLocation(lat, lng) {
    placeFormMode = 'new';
    picked = { lat, lng };
    $('pf-coords').textContent = `Lokasi: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    $('pf-error').classList.add('hidden');
    $('pf-title').value = '';
    $('pf-desc').value = '';
    $('pf-photo').value = '';
    $('pf-photo-wrap').classList.remove('hidden');
    fillListSelect($('pf-list'));
    $('place-form').classList.remove('hidden');
    $('place-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function editPlace(p) {
    placeFormMode = p.id;
    picked = { lat: p.lat, lng: p.lng };
    $('pf-coords').textContent = 'Edit tempat';
    $('pf-error').classList.add('hidden');
    $('pf-title').value = p.title;
    $('pf-desc').value = p.description || '';
    $('pf-photo').value = '';
    $('pf-photo-wrap').classList.add('hidden'); // foto tak diubah lewat edit (YAGNI)
    fillListSelect($('pf-list'), p.place_list_id);
    $('place-form').classList.remove('hidden');
    $('place-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function fillListSelect(sel, selectedId) {
    sel.innerHTML = '';
    lists.forEach((l) => {
      const o = document.createElement('option');
      o.value = l.id; o.textContent = l.name;
      if (selectedId && l.id === selectedId) o.selected = true;
      sel.appendChild(o);
    });
  }

  map.on('click', (e) => pickLocation(e.latlng.lat, e.latlng.lng));

  $('btn-my-location').onclick = () => {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition((pos) => {
      map.setView([pos.coords.latitude, pos.coords.longitude], 15);
      pickLocation(pos.coords.latitude, pos.coords.longitude);
    });
  };

  $('pf-cancel').onclick = () => { picked = null; placeFormMode = 'new'; $('place-form').classList.add('hidden'); };
  $('pf-save').onclick = () => {
    if (!picked) return;
    const title = $('pf-title').value.trim();
    if (!title) { showErr('pf-error', 'Nama tempat wajib diisi.'); return; }
    const isNew = placeFormMode === 'new';
    const done = () => {
      picked = null; placeFormMode = 'new';
      $('place-form').classList.add('hidden');
      ['pf-title', 'pf-desc'].forEach((id) => { $(id).value = ''; });
      $('pf-photo').value = '';
      refresh();
    };
    $('pf-save').disabled = true;

    let req;
    if (isNew) {
      const fd = new FormData();
      fd.append('place_list_id', $('pf-list').value);
      fd.append('lat', picked.lat);
      fd.append('lng', picked.lng);
      fd.append('title', title);
      fd.append('description', $('pf-desc').value.trim());
      if ($('pf-photo').files[0]) fd.append('photo', $('pf-photo').files[0]);
      req = fetch('/peta/titik', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, body: fd });
    } else {
      req = fetch(`/peta/titik/${placeFormMode}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body: JSON.stringify({ place_list_id: $('pf-list').value, title, description: $('pf-desc').value.trim() || null }),
      });
    }

    req.then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (!ok) { showErr('pf-error', firstError(body) || 'Gagal menyimpan.'); return; }
        done();
      })
      .catch(() => showErr('pf-error', 'Gagal menyimpan. Coba lagi.'))
      .finally(() => { $('pf-save').disabled = false; });
  };

  function firstError(body) {
    if (body && body.errors) return Object.values(body.errors)[0][0];
    return body && body.message;
  }
  function showErr(id, msg) { const e = $(id); e.textContent = msg; e.classList.remove('hidden'); }

  refresh();
})();
```

> Catatan implementer: tambah & edit tempat memakai form inline yang sama (`#place-form`). Mode `new` → POST multipart (dengan foto); mode edit (id tempat) → PATCH JSON (title/description/place_list_id, tanpa foto  input foto disembunyikan lewat `#pf-photo-wrap`). Tidak ada ketergantungan pada fitur pre-fill `MuterinDialog`. Konfirmasi hapus tetap pakai `window.MuterinDialog.confirm`.

- [ ] **Step 6: Verifikasi manual**

Run: buka `/peta/titik`. Konfirmasi: 3 list default muncul; klik peta → form simpan tempat (pilih list) → tempat muncul sebagai marker icon list-nya; hover marker → kartu foto+judul; klik marker → kartu penuh (edit/hapus); "+ Buat List" → pilih icon+warna → list baru muncul; klik list → filter; hapus list custom → konfirmasi lalu tempatnya ikut hilang.

- [ ] **Step 7: Commit**

```bash
git add resources/views/map/saved.blade.php public/js/map-saved.js routes/web.php resources/views/layouts/navigation.blade.php
git commit -m "feat: new Titik Saya page  list manager, save place with photo, icon markers, hover cards"
```

---

### Task 4: Pensiunkan `map_pins`

**Files:**
- Create: `database/migrations/2026_07_22_100003_migrate_map_pins_to_saved_places.php`
- Modify: `app/Http/Controllers/MapController.php`, `app/Models/User.php`, `routes/web.php`, `tests/Feature/MapTest.php`
- Delete: `app/Models/MapPin.php`, `resources/views/map/pins.blade.php`, `public/js/map-pins.js`

**Interfaces:**
- Consumes: `PlaceList::ensureDefaultsFor`, `SavedPlace` (Task 1).

- [ ] **Step 1: Migrasi data + drop tabel**

`database/migrations/2026_07_22_100003_migrate_map_pins_to_saved_places.php`:

```php
<?php

use App\Models\PlaceList;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('map_pins')) {
            return;
        }

        foreach (DB::table('map_pins')->get() as $pin) {
            $user = User::find($pin->user_id);
            if (! $user) {
                continue;
            }
            PlaceList::ensureDefaultsFor($user);
            $favorit = $user->placeLists()->where('name', 'Favorit')->where('is_default', true)->first();
            if (! $favorit) {
                continue;
            }
            $user->savedPlaces()->create([
                'place_list_id' => $favorit->id,
                'lat' => $pin->lat,
                'lng' => $pin->lng,
                'title' => $pin->title,
                'description' => $pin->note,
            ]);
        }

        Schema::dropIfExists('map_pins');
    }

    public function down(): void
    {
        // Retirement satu arah; tidak me-recreate map_pins.
    }
};
```

- [ ] **Step 2: Bersihkan `MapController`**

Di `app/Http/Controllers/MapController.php`:
- Hapus method `pinsPage()`, `storePin()`, `destroyPin()`.
- Hapus `use App\Models\MapPin;`.
- Di `data()`, hapus baris `'pins' => MapPin::where('user_id', $userId)->get(),` (sisakan `plans` & `trips`).

- [ ] **Step 3: Bersihkan route**

Di `routes/web.php`, hapus dua baris:

```php
    Route::post('map/pins', [MapController::class, 'storePin'])->name('map.pins.store');
    Route::delete('map/pins/{pin}', [MapController::class, 'destroyPin'])->name('map.pins.destroy');
```

- [ ] **Step 4: Bersihkan `User`**

Di `app/Models/User.php`, hapus method `mapPins()`.

- [ ] **Step 5: Hapus file usang**

```bash
git rm app/Models/MapPin.php resources/views/map/pins.blade.php public/js/map-pins.js
```

- [ ] **Step 6: Bersihkan test**

Di `tests/Feature/MapTest.php`, hapus 3 test yang pakai `/map/pins`: `test_user_can_store_and_list_own_pin`, `test_user_cannot_see_other_users_pins`, `test_user_can_delete_own_pin`. Hapus `use App\Models\MapPin;` bila ada. Test lain (route/plan/geocode/trips) tetap.

> Catatan: `test_map_data_excludes_recording_trips` membaca `data['trips']`  tetap valid setelah key `pins` dihapus. Pastikan tidak ada assertion di test lain yang mengharapkan key `pins`.

- [ ] **Step 7: Jalankan seluruh test**

Run: `php artisan test`
Expected: PASS semua. Migrasi data jalan lewat `RefreshDatabase` (tabel `map_pins` sudah tak dibuat ulang di test fresh; guard `Schema::hasTable` mencegah error).

- [ ] **Step 8: Verifikasi migrasi data (manual, dev DB)**

Karena `RefreshDatabase` memakai DB test terpisah, verifikasi migrasi data pada DB dev: jika ada baris `map_pins` sebelumnya, jalankan `php artisan migrate` dan konfirmasi barisnya kini muncul sebagai `saved_places` di list Favorit (via tinker atau halaman `/peta/titik`). Kalau dev DB tak punya `map_pins` lagi, cukup catat bahwa migrasi no-op (guard `hasTable`).

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor: retire map_pins  migrate pins to saved_places, remove old pin system"
```

---

## Catatan penutup

- Setelah semua task: `php artisan test` hijau; `storage:link` sudah ada.
- Foto tersimpan lokal (`storage/app/public/places`)  cukup untuk lomba/demo.
- Verifikasi manual paling penting: halaman `/peta/titik` baru jalan penuh (list CRUD, simpan tempat + foto, marker icon, hover, edit/hapus), dan data pin lama termigrasi (kalau ada).
