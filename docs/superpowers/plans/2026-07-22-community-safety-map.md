# Peta Komunitas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Peta keamanan komunitas: semua pengguna bisa menandai titik (sepi/gelap/rawan/rusak/banjir/momen) dengan foto & deskripsi, saling melihat, mengonfirmasi "masih di sini?", dan rute yang direncanakan diperingatkan bila melewati titik komunitas.

**Architecture:** Halaman publik baru `/peta/komunitas`. Dua tabel (`community_pins`, `community_pin_confirmations`), satu service `CommunityPinService` (kelayakan tampil, konfirmasi, kedekatan-ke-rute), endpoint di `CommunityController`, frontend `map-community.js` + `map/community.blade.php`. Peta Rencana yang sudah ada memanggil endpoint `near-route`.

**Tech Stack:** Laravel 13, PHP 8.4, Blade + Leaflet (CDN), Tailwind, SQLite (dev), disk lokal untuk foto.

## Global Constraints

- Tanpa layanan berbayar. Foto → `storage/app/public/community` via disk `public` + `storage:link`. Peta OSM, routing ORS free tier (sudah ada).
- Real-time = fresh-on-load + polling ~30 detik. Tidak ada WebSocket/broadcasting.
- Tidak ada `alert/confirm/prompt` native browser. Konfirmasi hapus pakai `window.AmictaDialog.confirm`. Form tambah-titik pakai **panel inline** di halaman (bukan dialog), karena ada upload foto.
- Titik komunitas terpisah total dari `map_pins`.
- Semua string UI Bahasa Indonesia.
- Kategori enum: `sepi, gelap, rawan, rusak, banjir, momen`. `time_context` enum: `siang, malam, kapanpun`.
- `confirm_count` = jumlah `still_there=true` − `still_there=false` (bisa negatif → kolom `integer` bertanda).
- Kelayakan tampil: titik disembunyikan bila `created_at` > 30 hari **dan** `confirm_count < 0`. Selain itu tampil.
- Cache-bust semua `public/js/*.js` baru: `?v={{ filemtime(...) }}`.
- Test pakai gaya PHPUnit class-based (lihat `tests/Feature/MapTest.php`), `RefreshDatabase`, `User::factory()`.

## File Structure

- Create: `database/migrations/xxxx_create_community_pins_table.php`
- Create: `database/migrations/xxxx_create_community_pin_confirmations_table.php`
- Create: `app/Models/CommunityPin.php`, `app/Models/CommunityPinConfirmation.php`
- Modify: `app/Models/User.php` (relasi `communityPins`)
- Create: `app/Services/CommunityPinService.php`
- Create: `app/Http/Controllers/CommunityController.php`
- Modify: `routes/web.php` (route grup auth), `resources/views/layouts/navigation.blade.php` (link)
- Create: `resources/views/map/community.blade.php`, `public/js/map-community.js`
- Modify: `public/js/map-plans.js`, `resources/views/map/plans.blade.php` (banner near-route)
- Create: `tests/Feature/CommunityPinTest.php`, `tests/Unit/CommunityPinServiceTest.php`

---

### Task 1: Migrasi, model, relasi

**Files:**
- Create: `database/migrations/2026_07_22_000001_create_community_pins_table.php`
- Create: `database/migrations/2026_07_22_000002_create_community_pin_confirmations_table.php`
- Create: `app/Models/CommunityPin.php`
- Create: `app/Models/CommunityPinConfirmation.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/CommunityPinTest.php` (baru, hanya test scope di task ini)

**Interfaces:**
- Produces: `CommunityPin` dengan `fillable`, casts, relasi `user()` & `confirmations()`, scope `visible()`. `CommunityPinConfirmation` dengan relasi. `User::communityPins()`.

- [ ] **Step 1: Migrasi `community_pins`**

`database/migrations/2026_07_22_000001_create_community_pins_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['sepi', 'gelap', 'rawan', 'rusak', 'banjir', 'momen']);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('time_context', ['siang', 'malam', 'kapanpun'])->default('kapanpun');
            $table->boolean('is_anonymous')->default(false);
            $table->integer('confirm_count')->default(0); // signed: "masih" - "udah nggak"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_pins');
    }
};
```

- [ ] **Step 2: Migrasi `community_pin_confirmations`**

`database/migrations/2026_07_22_000002_create_community_pin_confirmations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_pin_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_pin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('still_there');
            $table->timestamps();
            $table->unique(['community_pin_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_pin_confirmations');
    }
};
```

- [ ] **Step 3: Model `CommunityPinConfirmation`**

`app/Models/CommunityPinConfirmation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPinConfirmation extends Model
{
    protected $fillable = ['community_pin_id', 'user_id', 'still_there'];

    protected $casts = ['still_there' => 'boolean'];

    public function pin(): BelongsTo
    {
        return $this->belongsTo(CommunityPin::class, 'community_pin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Model `CommunityPin`**

`app/Models/CommunityPin.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPin extends Model
{
    protected $fillable = [
        'user_id', 'category', 'lat', 'lng', 'title', 'description',
        'photo_path', 'time_context', 'is_anonymous', 'confirm_count',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'confirm_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(CommunityPinConfirmation::class);
    }

    // Sembunyikan hanya bila tua (>30 hari) DAN mayoritas bilang "udah nggak".
    // ponytail: ambang 30 hari adalah tuning-knob.
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('created_at', '>=', now()->subDays(30))
                ->orWhere('confirm_count', '>=', 0);
        });
    }
}
```

- [ ] **Step 5: Relasi di `User`**

Tambahkan method ini ke `app/Models/User.php` (di dekat relasi lain seperti `mapPins()`):

```php
public function communityPins(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(CommunityPin::class);
}
```

- [ ] **Step 6: Test scope `visible()`**

`tests/Feature/CommunityPinTest.php`:

```php
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
        $oldButConfirmed->update(['created_at' => now()->subDays(40)]);
        $oldDisproven = $this->pin($u, ['confirm_count' => -1]);    // tua + dibantah -> hilang
        $oldDisproven->update(['created_at' => now()->subDays(40)]);

        $visibleIds = CommunityPin::visible()->pluck('id');

        $this->assertTrue($visibleIds->contains($fresh->id));
        $this->assertTrue($visibleIds->contains($oldButConfirmed->id));
        $this->assertFalse($visibleIds->contains($oldDisproven->id));
    }
}
```

- [ ] **Step 7: Jalankan test & migrasi**

Run: `php artisan test --filter=CommunityPinTest`
Expected: PASS (1 test). Migrasi otomatis jalan lewat `RefreshDatabase`.

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models tests/Feature/CommunityPinTest.php
git commit -m "feat: community_pins schema, models, visible scope"
```

---

### Task 2: CommunityPinService (kelayakan, konfirmasi, near-route)

**Files:**
- Create: `app/Services/CommunityPinService.php`
- Test: `tests/Unit/CommunityPinServiceTest.php`

**Interfaces:**
- Consumes: `CommunityPin`, `CommunityPinConfirmation`, `User` dari Task 1.
- Produces:
  - `visiblePins(): \Illuminate\Support\Collection` — pin `visible()` + relasi `user`, terbaru dulu.
  - `confirm(CommunityPin $pin, User $user, bool $stillThere): int` — simpan suara (1 user 1 suara), hitung ulang & kembalikan `confirm_count`.
  - `nearRoute(array $geometry, float $thresholdMeters = 300): \Illuminate\Support\Collection` — pin `visible()` yang jarak Haversine ke vertex terdekat pada polyline ≤ threshold.

- [ ] **Step 1: Tulis test unit**

`tests/Unit/CommunityPinServiceTest.php`:

```php
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
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=CommunityPinServiceTest`
Expected: FAIL ("Class CommunityPinService not found").

- [ ] **Step 3: Implementasi service**

`app/Services/CommunityPinService.php`:

```php
<?php

namespace App\Services;

use App\Models\CommunityPin;
use App\Models\CommunityPinConfirmation;
use App\Models\User;
use Illuminate\Support\Collection;

class CommunityPinService
{
    public function visiblePins(): Collection
    {
        return CommunityPin::visible()->with('user')->latest()->get();
    }

    public function confirm(CommunityPin $pin, User $user, bool $stillThere): int
    {
        CommunityPinConfirmation::updateOrCreate(
            ['community_pin_id' => $pin->id, 'user_id' => $user->id],
            ['still_there' => $stillThere],
        );

        $true = $pin->confirmations()->where('still_there', true)->count();
        $false = $pin->confirmations()->where('still_there', false)->count();
        $count = $true - $false;
        $pin->update(['confirm_count' => $count]);

        return $count;
    }

    // Pin yang jaraknya <= threshold meter dari vertex terdekat pada polyline rute.
    // ponytail: jarak ke vertex terdekat (bukan titik-ke-segmen) — geometry ORS rapat,
    // aproksimasi ini cukup; naikkan ke titik-ke-segmen kalau meleset.
    public function nearRoute(array $geometry, float $thresholdMeters = 300): Collection
    {
        if (count($geometry) < 1) {
            return collect();
        }

        return $this->visiblePins()->filter(function (CommunityPin $pin) use ($geometry, $thresholdMeters) {
            foreach ($geometry as [$lat, $lng]) {
                if ($this->haversine($pin->lat, $pin->lng, (float) $lat, (float) $lng) <= $thresholdMeters) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000; // meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=CommunityPinServiceTest`
Expected: PASS (4 test).

- [ ] **Step 5: Commit**

```bash
git add app/Services/CommunityPinService.php tests/Unit/CommunityPinServiceTest.php
git commit -m "feat: CommunityPinService — visible pins, confirm votes, near-route proximity"
```

---

### Task 3: Controller, route, nav, feature tests

**Files:**
- Create: `app/Http/Controllers/CommunityController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/CommunityPinTest.php` (tambah test)

**Interfaces:**
- Consumes: `CommunityPinService` (Task 2), `CommunityPin` (Task 1).
- Produces: endpoint JSON yang dipakai `map-community.js` (Task 4) & `map-plans.js` (Task 5):
  - `GET /peta/komunitas` → view.
  - `GET /peta/komunitas/data` → `{ pins: [...] }` (lihat bentuk payload di Step 2).
  - `POST /peta/komunitas` (multipart) → pin JSON, 201.
  - `POST /peta/komunitas/{pin}/confirm` → `{ confirm_count: int }`.
  - `POST /peta/komunitas/near-route` → `{ pins: [...] }`.
  - `DELETE /peta/komunitas/{pin}` → `{ ok: true }`.

- [ ] **Step 1: Controller**

`app/Http/Controllers/CommunityController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\CommunityPin;
use App\Services\CommunityPinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommunityController extends Controller
{
    public function __construct(private CommunityPinService $service) {}

    public function index()
    {
        return view('map.community', [
            'pins' => $this->service->visiblePins(),
        ]);
    }

    public function data()
    {
        return response()->json([
            'pins' => $this->service->visiblePins()->map(fn (CommunityPin $p) => $this->present($p)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|in:sepi,gelap,rawan,rusak,banjir,momen',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'time_context' => 'required|in:siang,malam,kapanpun',
            'is_anonymous' => 'boolean',
            'photo' => 'nullable|image|max:4096',
        ]);

        $data['photo_path'] = $request->file('photo')?->store('community', 'public');
        $data['is_anonymous'] = $request->boolean('is_anonymous');
        unset($data['photo']);

        $pin = $request->user()->communityPins()->create($data);

        return response()->json($this->present($pin->load('user')), 201);
    }

    public function confirm(Request $request, CommunityPin $pin)
    {
        $data = $request->validate(['still_there' => 'required|boolean']);
        $count = $this->service->confirm($pin, $request->user(), $data['still_there']);

        return response()->json(['confirm_count' => $count]);
    }

    public function nearRoute(Request $request)
    {
        $data = $request->validate([
            'geometry' => 'required|array|min:2',
            'geometry.*' => 'required|array|size:2',
            'geometry.*.0' => 'required|numeric|between:-90,90',
            'geometry.*.1' => 'required|numeric|between:-180,180',
        ]);

        return response()->json([
            'pins' => $this->service->nearRoute($data['geometry'])->map(fn (CommunityPin $p) => $this->present($p)),
        ]);
    }

    public function destroy(CommunityPin $pin)
    {
        abort_unless($pin->user_id === auth()->id(), 403);
        if ($pin->photo_path) {
            Storage::disk('public')->delete($pin->photo_path);
        }
        $pin->delete();

        return response()->json(['ok' => true]);
    }

    private function present(CommunityPin $p): array
    {
        return [
            'id' => $p->id,
            'category' => $p->category,
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'title' => $p->title,
            'description' => $p->description,
            'photo_url' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
            'time_context' => $p->time_context,
            'confirm_count' => $p->confirm_count,
            'contributor' => $p->is_anonymous ? null : $p->user?->name,
            'is_mine' => $p->user_id === auth()->id(),
        ];
    }
}
```

- [ ] **Step 2: Route**

Di `routes/web.php`, dalam grup `auth` (setelah baris `map.plans.destroy`, sebelum `});` penutup grup — sekitar baris 66), tambahkan:

```php
    // Peta Komunitas (publik antar-pengguna)
    Route::get('peta/komunitas', [CommunityController::class, 'index'])->name('map.community');
    Route::get('peta/komunitas/data', [CommunityController::class, 'data'])->name('map.community.data');
    Route::post('peta/komunitas', [CommunityController::class, 'store'])->name('map.community.store');
    Route::post('peta/komunitas/near-route', [CommunityController::class, 'nearRoute'])->name('map.community.near-route');
    Route::post('peta/komunitas/{pin}/confirm', [CommunityController::class, 'confirm'])->name('map.community.confirm');
    Route::delete('peta/komunitas/{pin}', [CommunityController::class, 'destroy'])->name('map.community.destroy');
```

Tambahkan import di atas file: `use App\Http\Controllers\CommunityController;`

> Perhatikan: definisikan route statis `peta/komunitas/near-route` **sebelum** route `peta/komunitas/{pin}/confirm` sudah otomatis aman karena keduanya beda method/segmen; tapi pastikan `near-route` (POST) tidak bentrok — aman karena `{pin}` binding hanya di `/confirm` dan `/{pin}` DELETE.

- [ ] **Step 3: Link navigasi**

Di `resources/views/layouts/navigation.blade.php`, tambahkan setelah baris `map.pins` (baris 10):

```php
        ['route' => 'map.community', 'pattern' => 'map.community', 'label' => 'Peta Komunitas', 'icon' => 'alert-triangle'],
```

- [ ] **Step 4: Feature tests**

Tambahkan ke `tests/Feature/CommunityPinTest.php` (dalam class yang sama). Tambahkan `use` yang diperlukan di atas: `use Illuminate\Http\UploadedFile;`, `use Illuminate\Support\Facades\Storage;`.

```php
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
            'photo' => UploadedFile::fake()->image('sunset.jpg'),
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
```

- [ ] **Step 5: Jalankan test**

Run: `php artisan test --filter=CommunityPinTest`
Expected: PASS (semua, termasuk scope test dari Task 1).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/CommunityController.php routes/web.php resources/views/layouts/navigation.blade.php tests/Feature/CommunityPinTest.php
git commit -m "feat: community pin endpoints (index/data/store/confirm/near-route/destroy) + nav"
```

---

### Task 4: Halaman & JS peta komunitas

**Files:**
- Create: `resources/views/map/community.blade.php`
- Create: `public/js/map-community.js`

**Interfaces:**
- Consumes: endpoint Task 3, `window.AmictaMap` (`init`, `token`, `fitTo`), `window.AmictaDialog.confirm`.
- Produces: halaman fungsional (tidak dipakai task lain).

- [ ] **Step 1: Pastikan symlink storage ada**

Run: `php artisan storage:link`
Expected: symlink `public/storage` → `storage/app/public` (atau "already exists"). Idempotent, aman diulang.

- [ ] **Step 2: Blade view**

`resources/views/map/community.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">Peta Komunitas</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <x-ui.hero badge="{{ $pins->count() }} titik" title="Peta Keamanan Komunitas"
                    subtitle="Titik dari semua pengguna. Tandai jalan sepi, gelap, rawan, rusak, atau banjir — bantu yang lain tetap aman." />

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            {{-- LEFT: filter + form + daftar --}}
            <div class="space-y-6">
                <div class="bg-surface border border-border rounded-2xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-heading font-bold text-foreground text-sm">Filter</h3>
                        <select id="filter-category" class="rounded-xl border border-border bg-surface px-3 py-1.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">Semua</option>
                            <option value="sepi">Jalan Sepi</option>
                            <option value="gelap">Penerangan Minim</option>
                            <option value="rawan">Rawan Kriminal</option>
                            <option value="rusak">Jalan Rusak</option>
                            <option value="banjir">Rawan Banjir</option>
                            <option value="momen">Momen</option>
                        </select>
                    </div>
                    <button id="btn-my-location" type="button"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-primary/10 text-primary hover:bg-primary/20 transition">
                        <x-icon.navigation class="w-4 h-4"/> Tandai Lokasi Saya
                    </button>
                    <p class="text-xs text-muted-fg leading-relaxed">Atau klik di mana saja di peta untuk menandai titik di sana.</p>
                </div>

                {{-- Form tambah titik (inline, tersembunyi sampai ada lokasi dipilih) --}}
                <div id="add-form" class="hidden bg-surface border border-primary/30 rounded-2xl p-5 space-y-3">
                    <h3 class="font-heading font-bold text-foreground text-sm">Tandai Titik Baru</h3>
                    <p id="add-coords" class="text-xs text-muted-fg"></p>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Kategori</span>
                        <select id="f-category" class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm">
                            <option value="sepi">Jalan Sepi</option>
                            <option value="gelap">Penerangan Minim</option>
                            <option value="rawan">Rawan Kriminal</option>
                            <option value="rusak">Jalan Rusak</option>
                            <option value="banjir">Rawan Banjir</option>
                            <option value="momen">Momen</option>
                        </select>
                    </label>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Judul</span>
                        <input id="f-title" type="text" maxlength="255" placeholder="mis. Jalan sepi & gelap"
                               class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </label>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Deskripsi (opsional)</span>
                        <textarea id="f-description" rows="2" maxlength="2000" placeholder="Ceritakan kondisinya…"
                                  class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                    </label>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Berlaku waktu</span>
                        <select id="f-time" class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm">
                            <option value="kapanpun">Kapan pun</option>
                            <option value="siang">Siang</option>
                            <option value="malam">Malam</option>
                        </select>
                    </label>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Foto (opsional)</span>
                        <input id="f-photo" type="file" accept="image/*"
                               class="w-full text-xs text-muted-fg file:mr-3 file:rounded-lg file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                    </label>

                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input id="f-anon" type="checkbox" class="rounded border-border text-primary focus:ring-primary/30">
                        Posting sebagai anonim
                    </label>

                    <p id="add-error" class="hidden text-xs text-accent"></p>

                    <div class="flex gap-2 pt-1">
                        <x-ui.button id="add-cancel" variant="outline" size="sm" type="button" class="flex-1 justify-center">Batal</x-ui.button>
                        <x-ui.button id="add-submit" variant="primary" size="sm" type="button" class="flex-1 justify-center">Tandai</x-ui.button>
                    </div>
                </div>

                <div class="bg-surface border border-border rounded-2xl overflow-hidden flex flex-col">
                    <div class="p-5 border-b border-border bg-muted/40">
                        <h3 class="font-heading font-bold text-foreground text-sm">Titik Terbaru</h3>
                    </div>
                    <div id="pin-list" class="p-3 space-y-1 overflow-y-auto" style="max-height: 44vh"></div>
                </div>
            </div>

            {{-- RIGHT: map --}}
            <div class="lg:col-span-2">
                <div class="rounded-2xl overflow-hidden border border-border">
                    <div id="map" style="height: 72vh"></div>
                </div>
            </div>
        </div>
    </div>

    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}?v={{ filemtime(public_path('js/map-common.js')) }}"></script>
    <script src="{{ asset('js/map-community.js') }}?v={{ filemtime(public_path('js/map-community.js')) }}"></script>
</x-app-layout>
```

- [ ] **Step 3: JavaScript**

`public/js/map-community.js`:

```javascript
(function () {
  const map = window.AmictaMap.init('map');
  const token = window.AmictaMap.token();
  const $ = (id) => document.getElementById(id);

  const CAT = {
    sepi:   { label: 'Jalan Sepi',        color: '#D97706' },
    gelap:  { label: 'Penerangan Minim',  color: '#6366F1' },
    rawan:  { label: 'Rawan Kriminal',    color: '#DC2626' },
    rusak:  { label: 'Jalan Rusak',       color: '#78716C' },
    banjir: { label: 'Rawan Banjir',      color: '#0EA5E9' },
    momen:  { label: 'Momen',             color: '#0F766E' },
  };
  const TIME = { siang: 'Siang', malam: 'Malam', kapanpun: 'Kapan pun' };

  let pins = [];
  let markers = new Map(); // id -> layer
  let picked = null;       // {lat, lng} lokasi yang sedang ditandai
  let filter = '';

  function catColor(c) { return (CAT[c] || {}).color || '#64748B'; }
  function catLabel(c) { return (CAT[c] || {}).label || c; }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }

  // --- Popup kartu titik ---
  function popupHtml(p) {
    const photo = p.photo_url
      ? `<img src="${esc(p.photo_url)}" alt="" style="width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:8px">` : '';
    const who = p.contributor ? `Ditandai oleh ${esc(p.contributor)}` : 'Ditandai oleh pengguna anonim';
    const del = p.is_mine
      ? `<button data-act="del" style="font-size:11px;color:#B91C1C;background:none;border:0;cursor:pointer;padding:0;margin-top:6px">Hapus titik</button>` : '';
    return `
      <div style="min-width:210px;max-width:230px">
        ${photo}
        <span style="display:inline-block;font-size:10px;font-weight:700;color:#fff;background:${catColor(p.category)};padding:2px 8px;border-radius:999px">${esc(catLabel(p.category))}</span>
        <p style="font-weight:700;font-size:14px;color:#0F172A;margin:6px 0 2px">${esc(p.title)}</p>
        ${p.description ? `<p style="font-size:12px;color:#475569;margin:0 0 4px">${esc(p.description)}</p>` : ''}
        <p style="font-size:11px;color:#64748B;margin:0">Berlaku: ${esc(TIME[p.time_context] || p.time_context)}</p>
        <p style="font-size:11px;color:#64748B;margin:2px 0 8px">${who}</p>
        <div style="display:flex;align-items:center;gap:6px">
          <button data-act="yes" style="flex:1;font-size:11px;font-weight:600;padding:6px;border-radius:8px;border:0;cursor:pointer;background:#ECFDF5;color:#047857">Masih di sini</button>
          <button data-act="no" style="flex:1;font-size:11px;font-weight:600;padding:6px;border-radius:8px;border:0;cursor:pointer;background:#FEF2F2;color:#B91C1C">Udah nggak</button>
        </div>
        <p style="font-size:11px;color:#64748B;margin:6px 0 0" data-count>Dikonfirmasi ${p.confirm_count} orang</p>
        ${del}
      </div>`;
  }

  function openPinPopup(p, latlng) {
    const el = document.createElement('div');
    el.innerHTML = popupHtml(p);
    const vote = (still) => {
      fetch(`/peta/komunitas/${p.id}/confirm`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body: JSON.stringify({ still_there: still }),
      }).then((r) => r.json()).then((b) => {
        p.confirm_count = b.confirm_count;
        el.querySelector('[data-count]').textContent = `Dikonfirmasi ${b.confirm_count} orang`;
      });
    };
    el.querySelector('[data-act="yes"]').onclick = () => vote(true);
    el.querySelector('[data-act="no"]').onclick = () => vote(false);
    const delBtn = el.querySelector('[data-act="del"]');
    if (delBtn) {
      delBtn.onclick = async () => {
        const ok = await window.AmictaDialog.confirm('Hapus titik ini?', { danger: true, confirmText: 'Hapus' });
        if (!ok) return;
        fetch(`/peta/komunitas/${p.id}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        }).then(() => { map.closePopup(); refresh(); });
      };
    }
    L.popup({ maxWidth: 250 }).setLatLng(latlng).setContent(el).openOn(map);
  }

  // --- Render markers + list ---
  function render() {
    const shown = pins.filter((p) => !filter || p.category === filter);
    const keep = new Set(shown.map((p) => p.id));

    markers.forEach((layer, id) => {
      if (!keep.has(id)) { layer.remove(); markers.delete(id); }
    });

    shown.forEach((p) => {
      if (markers.has(p.id)) return;
      const layer = L.circleMarker([p.lat, p.lng], {
        color: catColor(p.category), radius: 8, fillColor: catColor(p.category), fillOpacity: 1, weight: 2,
      }).addTo(map);
      layer.on('click', () => openPinPopup(p, [p.lat, p.lng]));
      markers.set(p.id, layer);
    });

    const list = $('pin-list');
    list.innerHTML = '';
    if (!shown.length) {
      list.innerHTML = '<div class="p-6 text-center text-sm text-muted-fg">Belum ada titik.</div>';
      return;
    }
    shown.forEach((p) => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'block w-full text-left p-3 rounded-xl hover:bg-muted/60 transition';
      row.innerHTML =
        `<div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:${catColor(p.category)}"></span>` +
        `<p class="font-bold text-sm text-foreground truncate">${esc(p.title)}</p></div>` +
        `<p class="text-[11px] text-muted-fg mt-0.5 ml-4.5">${esc(catLabel(p.category))} · ${p.contributor ? esc(p.contributor) : 'anonim'} · ${p.confirm_count} konfirmasi</p>`;
      row.onclick = () => { map.setView([p.lat, p.lng], 15); openPinPopup(p, [p.lat, p.lng]); };
      list.appendChild(row);
    });
  }

  // --- Fetch / refresh ---
  function refresh() {
    fetch('/peta/komunitas/data', { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((b) => { pins = b.pins || []; render(); })
      .catch(() => {});
  }

  // --- Add-pin flow ---
  function pickLocation(lat, lng) {
    picked = { lat, lng };
    $('add-coords').textContent = `Lokasi: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    $('add-error').classList.add('hidden');
    $('add-form').classList.remove('hidden');
    $('add-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  map.on('click', (e) => pickLocation(e.latlng.lat, e.latlng.lng));

  $('btn-my-location').onclick = () => {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition((pos) => {
      const { latitude, longitude } = pos.coords;
      map.setView([latitude, longitude], 15);
      pickLocation(latitude, longitude);
    });
  };

  $('add-cancel').onclick = () => { picked = null; $('add-form').classList.add('hidden'); };

  $('add-submit').onclick = () => {
    if (!picked) return;
    const title = $('f-title').value.trim();
    if (!title) { showAddError('Judul wajib diisi.'); return; }

    const fd = new FormData();
    fd.append('category', $('f-category').value);
    fd.append('lat', picked.lat);
    fd.append('lng', picked.lng);
    fd.append('title', title);
    fd.append('description', $('f-description').value.trim());
    fd.append('time_context', $('f-time').value);
    fd.append('is_anonymous', $('f-anon').checked ? 1 : 0);
    if ($('f-photo').files[0]) fd.append('photo', $('f-photo').files[0]);

    $('add-submit').disabled = true;
    fetch('/peta/komunitas', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: fd,
    })
      .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (!ok) { showAddError(firstError(body) || 'Gagal menyimpan titik.'); return; }
        resetForm();
        refresh();
      })
      .catch(() => showAddError('Gagal menyimpan titik. Coba lagi.'))
      .finally(() => { $('add-submit').disabled = false; });
  };

  function firstError(body) {
    if (body && body.errors) return Object.values(body.errors)[0][0];
    return body && body.message;
  }
  function showAddError(msg) { const e = $('add-error'); e.textContent = msg; e.classList.remove('hidden'); }
  function resetForm() {
    picked = null;
    $('add-form').classList.add('hidden');
    ['f-title', 'f-description'].forEach((id) => { $(id).value = ''; });
    $('f-photo').value = '';
    $('f-anon').checked = false;
  }

  $('filter-category').onchange = (e) => { filter = e.target.value; render(); };

  // --- Boot ---
  refresh();
  setInterval(refresh, 30000); // ponytail: polling 30s; ganti ke WebSocket kalau perlu
})();
```

- [ ] **Step 4: Verifikasi manual di browser**

Run: `php artisan serve` (jika belum jalan), buka `/peta/komunitas`. Klik peta → form muncul → isi judul + kategori → Tandai → titik muncul di peta & daftar. Klik marker → kartu popup dengan tombol konfirmasi. Klik "Masih di sini" → angka konfirmasi naik. Ganti filter → daftar & marker tersaring.

- [ ] **Step 5: Commit**

```bash
git add resources/views/map/community.blade.php public/js/map-community.js
git commit -m "feat: community map page — add-pin form, marker cards, confirm votes, filter, 30s refresh"
```

---

### Task 5: Integrasi peringatan ke Peta Rencana

**Files:**
- Modify: `resources/views/map/plans.blade.php`
- Modify: `public/js/map-plans.js`

**Interfaces:**
- Consumes: `POST /peta/komunitas/near-route` (Task 3) → `{ pins: [...] }` dengan field sama seperti payload `data`.
- Produces: banner peringatan di panel Peta Rencana + marker titik komunitas di peta rencana.

- [ ] **Step 1: Tambah elemen banner di Blade**

Di `resources/views/map/plans.blade.php`, tepat **setelah** blok `route-summary` (setelah `</div>` penutupnya, sebelum `<p id="route-status" ...>` di sekitar baris 59-61), sisipkan:

```blade
                    {{-- Peringatan titik komunitas di sepanjang rute --}}
                    <div id="community-warning" class="hidden p-3 rounded-xl bg-amber-50 border border-amber-200">
                        <p class="text-sm font-semibold text-amber-800 flex items-center gap-1.5">
                            <x-icon.alert-triangle class="w-4 h-4"/> <span id="community-warning-text"></span>
                        </p>
                        <p class="text-xs text-amber-700 mt-0.5">Titik-titik ini ditampilkan di peta. Hati-hati di area tersebut.</p>
                    </div>
```

- [ ] **Step 2: Tambah logika near-route di `map-plans.js`**

Di `public/js/map-plans.js`, di dalam blok sukses `maybeRoute()` (setelah baris `$('route-summary').classList.remove('hidden');` di sekitar baris 182), tambahkan pemanggilan:

```javascript
        checkCommunity(body.geometry);
```

Lalu tambahkan fungsi berikut dan variabel state di dekat deklarasi variabel atas file (setelah `let requestSeq = 0;`):

```javascript
  let communityMarkers = [];

  const CAT_LABEL = {
    sepi: 'sepi', gelap: 'gelap', rawan: 'rawan', rusak: 'rusak', banjir: 'banjir', momen: 'momen',
  };
  const CAT_COLOR = {
    sepi: '#D97706', gelap: '#6366F1', rawan: '#DC2626', rusak: '#78716C', banjir: '#0EA5E9', momen: '#0F766E',
  };

  function clearCommunity() {
    communityMarkers.forEach((m) => m.remove());
    communityMarkers = [];
    const w = document.getElementById('community-warning');
    if (w) w.classList.add('hidden');
  }

  function checkCommunity(geometry) {
    clearCommunity();
    fetch('/peta/komunitas/near-route', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ geometry }),
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((b) => {
        if (!b || !b.pins || !b.pins.length) return;
        const counts = {};
        b.pins.forEach((p) => {
          counts[p.category] = (counts[p.category] || 0) + 1;
          const color = CAT_COLOR[p.category] || '#64748B';
          const m = L.circleMarker([p.lat, p.lng], {
            color, radius: 7, fillColor: color, fillOpacity: 1, weight: 2,
          }).addTo(map).bindPopup(`<b>${p.title}</b><br>${CAT_LABEL[p.category] || p.category}`);
          communityMarkers.push(m);
        });
        const parts = Object.entries(counts).map(([c, n]) => `${n} ${CAT_LABEL[c] || c}`);
        const el = document.getElementById('community-warning');
        const txt = document.getElementById('community-warning-text');
        if (el && txt) {
          txt.textContent = `Rutemu lewat ${b.pins.length} titik komunitas (${parts.join(', ')}).`;
          el.classList.remove('hidden');
        }
      })
      .catch(() => {}); // ponytail: non-fatal, jangan ganggu alur rute
  }
```

Juga panggil `clearCommunity()` di dalam `clearRoute()` (setelah `$('route-summary').classList.add('hidden');`) supaya reset rute ikut membersihkan peringatan.

- [ ] **Step 3: Verifikasi manual**

Run: buka `/peta/komunitas`, buat titik `rawan` di suatu lokasi. Lalu buka `/peta/rencana`, buat rute yang lewat dekat titik itu (≤300m). Setelah rute terhitung, banner amber muncul: "Rutemu lewat 1 titik komunitas (1 rawan)." dan marker muncul di peta. Reset rute → banner & marker hilang.

- [ ] **Step 4: Commit**

```bash
git add resources/views/map/plans.blade.php public/js/map-plans.js
git commit -m "feat: warn on Peta Rencana when a planned route passes community pins"
```

---

## Catatan penutup

- Setelah semua task: `php artisan test` full harus hijau (baseline sebelumnya 125 test).
- `storage:link` wajib sudah dijalankan agar foto tampil.
- Foto disimpan lokal — cukup untuk lomba/demo. Migrasi ke cloud storage di luar cakupan.
