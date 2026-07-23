# Maps Planner Google-Style Implementation Plan (Plan 1 of 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn Peta Rencana into a Google-Maps-style planner  search box (geocoding), click-a-point-to-see-its-address, explicit "set as start / destination / via" confirmation, and a route summary panel  and replace every native browser `prompt()`/`confirm()`/`alert()` across the app with one styled dialog component.

**Architecture:** A reusable `MuterinDialog` (Blade partial in the app layout + `public/js/dialog.js`) exposes async `confirm`/`prompt`/`alert`. A backend `GeocodingService` proxies OpenRouteService's Geocode API (forward search + reverse) server-side, exposed via two `GET /map/geocode/*` endpoints  same proxy pattern as the existing `RouteService`. The planner frontend (`public/js/map-plans.js` + `resources/views/map/plans.blade.php`) is rewritten to consume these plus the already-existing `POST /map/route`.

**Tech Stack:** Laravel 13, Leaflet.js (CDN, already used), OpenRouteService Geocode API, Laravel `Http` facade, vanilla JS + Alpine (no new dependency).

## Global Constraints

- API key: `ORS_API_KEY` is already in `.env` and `config('services.ors.key')` already exists (added in the previous routing round). Do not touch `.env`/`.env.example`.
- OpenRouteService coordinate order is `[lng, lat]`  the opposite of `[lat, lng]` used everywhere else in this codebase. `GeocodingService` is the ONLY place that converts; every other file works in `[lat, lng]`.
- Service-class pattern: pure PHP class in `app/Services/`, method-injected into controllers (same as `RouteService`/`OdometerService`).
- Error copy (exact): geocoding failure → `"Gagal mencari lokasi. Coba lagi sebentar."`; routing failure (unchanged from before) → `"Gagal menghitung rute jalan. Coba lagi sebentar."`. Never silently fall back to a straight line.
- Reverse-geocode with no result must still return a usable location: `label = "Lokasi tanpa nama"`, with the queried lat/lng.
- Search is triggered on submit (Enter / button), NOT on every keystroke  ORS quota is shared across routing + geocoding (2000/day).
- `public/js/*.js` files are plain static assets served via `asset()`, not part of the Vite build  editing them takes effect immediately, no `npm run build` needed. The dialog Blade partial (Tailwind classes) IS covered by the existing Vite CSS build, but uses only classes already present elsewhere in the app, so no new build is required for it to style correctly.
- `MuterinDialog.prompt` resolves: a trimmed non-empty string in single-field mode, or `{value, extra}` when an `extra` field is configured, or `null` on cancel. `MuterinDialog.confirm` resolves `true`/`false`. `MuterinDialog.alert` resolves `undefined`.
- Commit directly to `master` (no worktree  established convention).
- TDD for backend (RED→GREEN). Frontend-only tasks have no new automated tests (consistent with the rest of this codebase's JS)  they are verified manually in the browser by the controller in the final task.

---

### Task 1: Reusable MuterinDialog component

**Files:**
- Create: `resources/views/components/ui/dialog.blade.php`
- Create: `public/js/dialog.js`
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Produces (global, on every authenticated page): `window.MuterinDialog.confirm(message, {confirmText, cancelText, danger}) : Promise<boolean>`, `window.MuterinDialog.prompt(message, {label, placeholder, defaultValue, confirmText, extra}) : Promise<string|{value,extra}|null>`, `window.MuterinDialog.alert(message, {confirmText}) : Promise<void>`.

- [ ] **Step 1: Create the dialog Blade partial**

`resources/views/components/ui/dialog.blade.php`:

```blade
<div id="Muterin-dialog" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" role="dialog" aria-modal="true">
    <div data-dialog-backdrop class="absolute inset-0 bg-slate-900/50"></div>
    <div class="relative bg-surface rounded-2xl border border-border shadow-lift w-full max-w-sm p-6">
        <p data-dialog-message class="text-sm text-foreground font-medium"></p>
        <div data-dialog-fields class="mt-4 space-y-3 hidden">
            <label class="block space-y-1.5">
                <span data-dialog-input-label class="text-xs font-medium text-muted-fg"></span>
                <input data-dialog-input type="text"
                       class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            <label data-dialog-extra-wrap class="block space-y-1.5 hidden">
                <span data-dialog-extra-label class="text-xs font-medium text-muted-fg"></span>
                <textarea data-dialog-extra rows="2"
                          class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"></textarea>
            </label>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button data-dialog-cancel type="button"
                    class="inline-flex items-center justify-center gap-2 font-heading font-semibold rounded-xl transition text-sm px-4 py-2.5 border border-border bg-surface text-foreground hover:bg-muted cursor-pointer">Batal</button>
            <button data-dialog-confirm type="button"
                    class="inline-flex items-center justify-center gap-2 font-heading font-semibold rounded-xl transition text-sm px-4 py-2.5 bg-primary text-white hover:bg-primary-hover cursor-pointer disabled:opacity-50 disabled:pointer-events-none">OK</button>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Create dialog.js**

`public/js/dialog.js`:

```js
(function () {
  const root = document.getElementById('Muterin-dialog');
  if (!root) return;

  const backdrop = root.querySelector('[data-dialog-backdrop]');
  const messageEl = root.querySelector('[data-dialog-message]');
  const fieldsEl = root.querySelector('[data-dialog-fields]');
  const inputLabel = root.querySelector('[data-dialog-input-label]');
  const input = root.querySelector('[data-dialog-input]');
  const extraWrap = root.querySelector('[data-dialog-extra-wrap]');
  const extraLabel = root.querySelector('[data-dialog-extra-label]');
  const extra = root.querySelector('[data-dialog-extra]');
  const cancelBtn = root.querySelector('[data-dialog-cancel]');
  const confirmBtn = root.querySelector('[data-dialog-confirm]');

  const BTN_PRIMARY = 'inline-flex items-center justify-center gap-2 font-heading font-semibold rounded-xl transition text-sm px-4 py-2.5 bg-primary text-white hover:bg-primary-hover cursor-pointer disabled:opacity-50 disabled:pointer-events-none';
  const BTN_DANGER = 'inline-flex items-center justify-center gap-2 font-heading font-semibold rounded-xl transition text-sm px-4 py-2.5 bg-accent text-white hover:bg-accent-hover cursor-pointer disabled:opacity-50 disabled:pointer-events-none';

  let resolver = null;
  let mode = 'confirm';
  let hasExtra = false;

  function open() { root.classList.remove('hidden'); root.classList.add('flex'); }
  function close() { root.classList.add('hidden'); root.classList.remove('flex'); }

  function settle(result) {
    const r = resolver;
    resolver = null;
    close();
    if (r) r(result);
  }

  function onConfirm() {
    if (mode === 'prompt') {
      const value = input.value.trim();
      if (!value) return; // required
      settle(hasExtra ? { value, extra: extra.value } : value);
    } else if (mode === 'confirm') {
      settle(true);
    } else {
      settle(undefined);
    }
  }

  function onCancel() {
    if (mode === 'prompt') settle(null);
    else if (mode === 'confirm') settle(false);
    else settle(undefined);
  }

  confirmBtn.addEventListener('click', onConfirm);
  cancelBtn.addEventListener('click', onCancel);
  backdrop.addEventListener('click', onCancel);
  document.addEventListener('keydown', (e) => {
    if (root.classList.contains('hidden')) return;
    if (e.key === 'Escape') onCancel();
    else if (e.key === 'Enter' && mode !== 'prompt') onConfirm();
  });
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); onConfirm(); } });
  input.addEventListener('input', () => { confirmBtn.disabled = !input.value.trim(); });

  window.MuterinDialog = {
    confirm(message, opts = {}) {
      mode = 'confirm'; hasExtra = false;
      messageEl.textContent = message;
      fieldsEl.classList.add('hidden');
      cancelBtn.classList.remove('hidden');
      confirmBtn.textContent = opts.confirmText || 'Ya';
      cancelBtn.textContent = opts.cancelText || 'Batal';
      confirmBtn.className = opts.danger ? BTN_DANGER : BTN_PRIMARY;
      confirmBtn.disabled = false;
      open();
      return new Promise((res) => { resolver = res; });
    },
    prompt(message, opts = {}) {
      mode = 'prompt'; hasExtra = !!opts.extra;
      messageEl.textContent = message;
      fieldsEl.classList.remove('hidden');
      inputLabel.textContent = opts.label || '';
      input.placeholder = opts.placeholder || '';
      input.value = opts.defaultValue || '';
      if (hasExtra) {
        extraWrap.classList.remove('hidden');
        extraLabel.textContent = opts.extra.label || '';
        extra.placeholder = opts.extra.placeholder || '';
        extra.value = '';
      } else {
        extraWrap.classList.add('hidden');
      }
      cancelBtn.classList.remove('hidden');
      cancelBtn.textContent = 'Batal';
      confirmBtn.textContent = opts.confirmText || 'Simpan';
      confirmBtn.className = BTN_PRIMARY;
      confirmBtn.disabled = !input.value.trim();
      open();
      setTimeout(() => input.focus(), 0);
      return new Promise((res) => { resolver = res; });
    },
    alert(message, opts = {}) {
      mode = 'alert'; hasExtra = false;
      messageEl.textContent = message;
      fieldsEl.classList.add('hidden');
      cancelBtn.classList.add('hidden');
      confirmBtn.textContent = opts.confirmText || 'OK';
      confirmBtn.className = BTN_PRIMARY;
      confirmBtn.disabled = false;
      open();
      return new Promise((res) => { resolver = res; });
    },
  };
})();
```

- [ ] **Step 3: Include the dialog partial + script globally in the app layout**

In `resources/views/layouts/app.blade.php`, find:

```blade
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
```

Replace with:

```blade
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-ui.dialog />
        <script src="{{ asset('js/dialog.js') }}"></script>
    </body>
</html>
```

- [ ] **Step 4: Run the full test suite (nothing should break)**

Run: `php artisan test`
Expected: all pass (no backend logic changed; this is additive frontend markup + a global script).

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/ui/dialog.blade.php public/js/dialog.js resources/views/layouts/app.blade.php
git commit -m "feat: reusable MuterinDialog (styled confirm/prompt/alert) available app-wide"
```

---

### Task 2: GeocodingService + endpoints (backend proxy)

**Files:**
- Create: `app/Exceptions/GeocodingException.php`
- Create: `app/Services/GeocodingService.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/MapController.php`
- Test: `tests/Unit/GeocodingServiceTest.php`, `tests/Feature/MapTest.php`

**Interfaces:**
- Produces:
  - `App\Services\GeocodingService::search(string $query, ?float $focusLat = null, ?float $focusLng = null): array` → list of `['label' => string, 'lat' => float, 'lng' => float]` (empty array if no results); throws `App\Exceptions\GeocodingException` on HTTP failure.
  - `App\Services\GeocodingService::reverse(float $lat, float $lng): array` → `['label' => string, 'lat' => float, 'lng' => float]` (label `"Lokasi tanpa nama"` if no feature); throws `GeocodingException` on HTTP failure.
  - Routes `map.geocode.search` (GET `/map/geocode/search`), `map.geocode.reverse` (GET `/map/geocode/reverse`).

- [ ] **Step 1: Write the failing tests (unit)**

Create `tests/Unit/GeocodingServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Exceptions\GeocodingException;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    public function test_search_parses_results_and_converts_coordinate_order(): void
    {
        Http::fake([
            'api.openrouteservice.org/geocode/search*' => Http::response([
                'features' => [
                    ['properties' => ['label' => 'Jalan Bintaro, Jakarta'], 'geometry' => ['coordinates' => [106.75, -6.27]]],
                    ['properties' => ['label' => 'Bintaro Plaza'], 'geometry' => ['coordinates' => [106.76, -6.28]]],
                ],
            ], 200),
        ]);

        $result = (new GeocodingService())->search('bintaro');

        $this->assertCount(2, $result);
        $this->assertEquals(['label' => 'Jalan Bintaro, Jakarta', 'lat' => -6.27, 'lng' => 106.75], $result[0]);
    }

    public function test_search_returns_empty_array_when_no_features(): void
    {
        Http::fake(['api.openrouteservice.org/geocode/search*' => Http::response(['features' => []], 200)]);

        $this->assertSame([], (new GeocodingService())->search('zzznowhere'));
    }

    public function test_search_throws_on_http_failure(): void
    {
        Http::fake(['api.openrouteservice.org/geocode/search*' => Http::response(['error' => 'quota'], 429)]);

        $this->expectException(GeocodingException::class);
        (new GeocodingService())->search('bintaro');
    }

    public function test_reverse_returns_nearest_label(): void
    {
        Http::fake([
            'api.openrouteservice.org/geocode/reverse*' => Http::response([
                'features' => [
                    ['properties' => ['label' => 'Jalan Merpati, Bintaro'], 'geometry' => ['coordinates' => [106.75, -6.27]]],
                ],
            ], 200),
        ]);

        $result = (new GeocodingService())->reverse(-6.27, 106.75);

        $this->assertEquals('Jalan Merpati, Bintaro', $result['label']);
        $this->assertEquals(-6.27, $result['lat']);
        $this->assertEquals(106.75, $result['lng']);
    }

    public function test_reverse_falls_back_when_no_feature(): void
    {
        Http::fake(['api.openrouteservice.org/geocode/reverse*' => Http::response(['features' => []], 200)]);

        $result = (new GeocodingService())->reverse(-6.5, 106.5);

        $this->assertEquals('Lokasi tanpa nama', $result['label']);
        $this->assertEquals(-6.5, $result['lat']);
        $this->assertEquals(106.5, $result['lng']);
    }

    public function test_reverse_throws_on_http_failure(): void
    {
        Http::fake(['api.openrouteservice.org/geocode/reverse*' => Http::response(['error' => 'down'], 500)]);

        $this->expectException(GeocodingException::class);
        (new GeocodingService())->reverse(-6.27, 106.75);
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --filter=GeocodingServiceTest`
Expected: FAIL  `App\Services\GeocodingService` and `App\Exceptions\GeocodingException` don't exist.

- [ ] **Step 3: Create the exception**

`app/Exceptions/GeocodingException.php`:

```php
<?php

namespace App\Exceptions;

use Exception;

class GeocodingException extends Exception
{
}
```

- [ ] **Step 4: Create GeocodingService**

`app/Services/GeocodingService.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\GeocodingException;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    private const ERROR_MESSAGE = 'Gagal mencari lokasi. Coba lagi sebentar.';

    public function search(string $query, ?float $focusLat = null, ?float $focusLng = null): array
    {
        $params = ['text' => $query, 'size' => 5];
        if ($focusLat !== null && $focusLng !== null) {
            $params['focus.point.lat'] = $focusLat;
            $params['focus.point.lon'] = $focusLng;
        }

        $response = Http::withHeaders(['Authorization' => config('services.ors.key')])
            ->timeout(8)
            ->get('https://api.openrouteservice.org/geocode/search', $params);

        if ($response->failed()) {
            throw new GeocodingException(self::ERROR_MESSAGE);
        }

        return collect($response->json('features', []))
            ->map(fn ($f) => [
                'label' => $f['properties']['label'] ?? 'Lokasi tanpa nama',
                'lat' => $f['geometry']['coordinates'][1],
                'lng' => $f['geometry']['coordinates'][0],
            ])
            ->values()
            ->all();
    }

    public function reverse(float $lat, float $lng): array
    {
        $response = Http::withHeaders(['Authorization' => config('services.ors.key')])
            ->timeout(8)
            ->get('https://api.openrouteservice.org/geocode/reverse', [
                'point.lat' => $lat,
                'point.lon' => $lng,
                'size' => 1,
            ]);

        if ($response->failed()) {
            throw new GeocodingException(self::ERROR_MESSAGE);
        }

        $feature = $response->json('features.0');

        if (!$feature) {
            return ['label' => 'Lokasi tanpa nama', 'lat' => $lat, 'lng' => $lng];
        }

        return [
            'label' => $feature['properties']['label'] ?? 'Lokasi tanpa nama',
            'lat' => $lat,
            'lng' => $lng,
        ];
    }
}
```

- [ ] **Step 5: Run unit tests to verify they pass**

Run: `php artisan test --filter=GeocodingServiceTest`
Expected: PASS (6 tests)

- [ ] **Step 6: Write the failing feature tests (endpoints)**

In `tests/Feature/MapTest.php`, add to the class (the `use Illuminate\Support\Facades\Http;` import is already present):

```php
    public function test_geocode_search_returns_results(): void
    {
        Http::fake([
            'api.openrouteservice.org/geocode/search*' => Http::response([
                'features' => [
                    ['properties' => ['label' => 'Bintaro'], 'geometry' => ['coordinates' => [106.75, -6.27]]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/map/geocode/search?q=bintaro')
            ->assertOk()
            ->assertJson(['results' => [['label' => 'Bintaro', 'lat' => -6.27, 'lng' => 106.75]]]);
    }

    public function test_geocode_search_requires_min_2_chars(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/map/geocode/search?q=a')->assertStatus(422);
    }

    public function test_geocode_search_returns_422_on_failure(): void
    {
        Http::fake(['api.openrouteservice.org/geocode/search*' => Http::response(['error' => 'quota'], 429)]);

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/map/geocode/search?q=bintaro')
            ->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_geocode_reverse_returns_label(): void
    {
        Http::fake([
            'api.openrouteservice.org/geocode/reverse*' => Http::response([
                'features' => [['properties' => ['label' => 'Jalan Merpati'], 'geometry' => ['coordinates' => [106.75, -6.27]]]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/map/geocode/reverse?lat=-6.27&lng=106.75')
            ->assertOk()->assertJson(['label' => 'Jalan Merpati']);
    }

    public function test_geocode_reverse_validates_coordinates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/map/geocode/reverse?lat=999&lng=106.75')->assertStatus(422);
    }

    public function test_geocode_requires_authentication(): void
    {
        $this->getJson('/map/geocode/search?q=bintaro')->assertStatus(401);
    }
```

- [ ] **Step 7: Add routes**

In `routes/web.php`, find:

```php
    Route::post('map/route', [MapController::class, 'previewRoute'])->name('map.route');
```

Replace with:

```php
    Route::post('map/route', [MapController::class, 'previewRoute'])->name('map.route');
    Route::get('map/geocode/search', [MapController::class, 'geocodeSearch'])->name('map.geocode.search');
    Route::get('map/geocode/reverse', [MapController::class, 'geocodeReverse'])->name('map.geocode.reverse');
```

- [ ] **Step 8: Add controller methods**

In `app/Http/Controllers/MapController.php`, add these imports alongside the existing ones:

```php
use App\Exceptions\GeocodingException;
use App\Services\GeocodingService;
```

Then add these two methods (e.g. right after `previewRoute()`):

```php
    public function geocodeSearch(Request $request, GeocodingService $geocoding)
    {
        $data = $request->validate([
            'q' => 'required|string|min:2',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $results = $geocoding->search($data['q'], $data['lat'] ?? null, $data['lng'] ?? null);

            return response()->json(['results' => $results]);
        } catch (GeocodingException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function geocodeReverse(Request $request, GeocodingService $geocoding)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        try {
            return response()->json($geocoding->reverse($data['lat'], $data['lng']));
        } catch (GeocodingException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --filter=MapTest`
Expected: PASS (all pre-existing MapTest tests + 6 new geocode tests)

- [ ] **Step 10: Commit**

```bash
git add app/Exceptions/GeocodingException.php app/Services/GeocodingService.php routes/web.php app/Http/Controllers/MapController.php tests/Unit/GeocodingServiceTest.php tests/Feature/MapTest.php
git commit -m "feat: GeocodingService + /map/geocode search & reverse endpoints"
```

---

### Task 3: route_plans start/end labels

**Files:**
- Create: `database/migrations/2026_07_21_110000_add_labels_to_route_plans_table.php`
- Modify: `app/Models/RoutePlan.php`
- Modify: `app/Http/Controllers/MapController.php`
- Test: `tests/Feature/MapTest.php`

**Interfaces:**
- Produces: `route_plans` gains `start_label` (nullable string) and `end_label` (nullable string). `storePlan` accepts optional `start_label`/`end_label` and stores them.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/MapTest.php`, add:

```php
    public function test_saving_route_plan_stores_start_and_end_labels(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/map/plans', [
            'name' => 'Rute berlabel',
            'points' => [[-6.2, 106.8], [-6.22, 106.82]],
            'route_geometry' => [[-6.2, 106.8], [-6.22, 106.82]],
            'distance_km' => 2.0,
            'duration_minutes' => 8,
            'start_label' => 'Rumah',
            'end_label' => 'Kantor',
        ])->assertCreated();

        $this->assertDatabaseHas('route_plans', [
            'name' => 'Rute berlabel', 'start_label' => 'Rumah', 'end_label' => 'Kantor',
        ]);
    }

    public function test_saving_route_plan_still_works_without_labels(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/map/plans', [
            'name' => 'Tanpa label',
            'points' => [[-6.2, 106.8], [-6.22, 106.82]],
            'route_geometry' => [[-6.2, 106.8], [-6.22, 106.82]],
            'distance_km' => 2.0,
            'duration_minutes' => 8,
        ])->assertCreated();

        $this->assertDatabaseHas('route_plans', ['name' => 'Tanpa label', 'start_label' => null]);
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=MapTest`
Expected: FAIL  `start_label`/`end_label` columns don't exist and aren't stored.

- [ ] **Step 3: Create the migration**

`database/migrations/2026_07_21_110000_add_labels_to_route_plans_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->string('start_label')->nullable()->after('duration_minutes');
            $table->string('end_label')->nullable()->after('start_label');
        });
    }

    public function down(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->dropColumn(['start_label', 'end_label']);
        });
    }
};
```

- [ ] **Step 4: Update the RoutePlan model**

In `app/Models/RoutePlan.php`, find:

```php
    protected $fillable = ['user_id', 'name', 'points_json', 'route_geometry_json', 'distance_km', 'duration_minutes'];
```

Replace with:

```php
    protected $fillable = ['user_id', 'name', 'points_json', 'route_geometry_json', 'distance_km', 'duration_minutes', 'start_label', 'end_label'];
```

- [ ] **Step 5: Update storePlan()**

In `app/Http/Controllers/MapController.php`, find:

```php
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'points' => 'required|array|min:2',
            'route_geometry' => 'required|array|min:2',
            'distance_km' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:0',
        ]);
        $plan = auth()->user()->routePlans()->create([
            'name' => $data['name'],
            'points_json' => $data['points'],
            'route_geometry_json' => $data['route_geometry'],
            'distance_km' => $data['distance_km'],
            'duration_minutes' => $data['duration_minutes'],
        ]);
```

Replace with:

```php
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'points' => 'required|array|min:2',
            'route_geometry' => 'required|array|min:2',
            'distance_km' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:0',
            'start_label' => 'nullable|string|max:255',
            'end_label' => 'nullable|string|max:255',
        ]);
        $plan = auth()->user()->routePlans()->create([
            'name' => $data['name'],
            'points_json' => $data['points'],
            'route_geometry_json' => $data['route_geometry'],
            'distance_km' => $data['distance_km'],
            'duration_minutes' => $data['duration_minutes'],
            'start_label' => $data['start_label'] ?? null,
            'end_label' => $data['end_label'] ?? null,
        ]);
```

- [ ] **Step 6: Migrate and test**

Run: `php artisan migrate --no-interaction && php artisan test --filter=MapTest`
Expected: PASS (all MapTest tests, including the 2 new label tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_21_110000_add_labels_to_route_plans_table.php app/Models/RoutePlan.php app/Http/Controllers/MapController.php tests/Feature/MapTest.php
git commit -m "feat: route_plans stores start/end labels for saved-plan summaries"
```

---

### Task 4: Peta Rencana  Google-Maps-style frontend

**Files:**
- Modify: `resources/views/map/plans.blade.php`
- Modify: `public/js/map-plans.js`

**Interfaces:**
- Consumes: `GET /map/geocode/search`, `GET /map/geocode/reverse` (Task 2), `POST /map/route` (existing), `POST /map/plans` with `start_label`/`end_label` (Task 3), `window.MuterinDialog` (Task 1), `window.MuterinMap` (existing).

- [ ] **Step 1: Rewrite the plans view**

Replace the full contents of `resources/views/map/plans.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">Rencana Rute</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <x-ui.hero badge="{{ $plans->count() }} rencana" title="Rencanakan Rute"
                    subtitle="Cari tempat atau klik di peta, pilih titik awal & tujuan  rute jalan otomatis dihitung." />

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="relative rounded-2xl overflow-hidden border border-border">
                    <div id="map" style="height: 62vh"></div>

                    {{-- Search box (floating top) --}}
                    <div class="absolute top-3 left-3 right-3 z-[1000] max-w-md">
                        <div class="bg-surface rounded-xl shadow-lift border border-border flex items-center gap-2 px-3 py-2">
                            <x-icon.search class="w-4 h-4 text-muted-fg shrink-0"/>
                            <input id="search-input" type="text" placeholder="Cari tempat (mis. Bintaro Plaza)…"
                                   class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-fg" autocomplete="off">
                            <button id="search-btn" type="button" class="text-sm font-semibold text-primary hover:underline shrink-0">Cari</button>
                        </div>
                        <div id="search-results" class="mt-1 bg-surface rounded-xl shadow-lift border border-border overflow-hidden hidden"></div>
                    </div>

                    {{-- Location info panel (floating, hidden until a point is clicked/picked) --}}
                    <div id="info-panel" class="absolute bottom-3 left-3 right-3 z-[1000] max-w-md bg-surface rounded-xl shadow-lift border border-border p-4 hidden">
                        <p id="info-label" class="font-heading font-semibold text-sm text-foreground"></p>
                        <p id="info-coords" class="text-[11px] text-muted-fg mt-0.5 tabular-nums"></p>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <button id="btn-set-start" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg bg-status-green/15 text-status-green hover:bg-status-green/25 transition">Jadikan Titik Awal</button>
                            <button id="btn-set-end" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg bg-accent/15 text-accent hover:bg-accent/25 transition">Jadikan Titik Tujuan</button>
                            <button id="btn-add-via" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg bg-amber-500/15 text-amber-600 hover:bg-amber-500/25 transition hidden">Tambah Titik Singgah</button>
                        </div>
                    </div>

                    {{-- Route summary panel (floating, hidden until a route exists) --}}
                    <div id="route-panel" class="absolute bottom-3 left-3 right-3 z-[1000] max-w-md bg-surface rounded-xl shadow-lift border border-border p-4 hidden">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-status-green shrink-0"></span>
                                <span id="route-start-label" class="text-sm text-foreground truncate"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-accent shrink-0"></span>
                                <span id="route-end-label" class="text-sm text-foreground truncate"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 mt-3 pt-3 border-t border-border">
                            <p class="text-sm"><span id="route-distance" class="font-heading font-bold text-foreground"></span></p>
                            <p class="text-sm text-muted-fg"><span id="route-duration"></span></p>
                            <div class="ms-auto flex gap-2">
                                <button id="reset-plan" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg border border-border text-foreground hover:bg-muted transition">Reset</button>
                                <button id="save-plan" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg bg-primary text-white hover:bg-primary-hover transition">Simpan</button>
                            </div>
                        </div>
                    </div>
                </div>
                <p id="route-status" class="text-sm text-muted-fg mt-2"></p>
            </div>

            <div class="bg-surface border border-border rounded-2xl overflow-hidden flex flex-col">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm">Rencana Tersimpan</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Klik untuk pratinjau di peta</p>
                </div>
                <div class="p-3 space-y-1 overflow-y-auto" style="max-height: 62vh">
                    @forelse ($plans as $plan)
                        <div class="p-3 rounded-xl hover:bg-muted/60 transition flex items-center justify-between gap-3">
                            <button data-view-plan="{{ $plan->id }}" class="min-w-0 text-left flex-1">
                                <p class="font-bold text-sm text-foreground truncate">{{ $plan->name }}</p>
                                <p class="text-[11px] text-muted-fg mt-0.5">
                                    @if ($plan->distance_km)
                                        {{ $plan->distance_km }} km &middot; {{ $plan->duration_minutes }} menit
                                    @else
                                        {{ count($plan->points_json) }} titik
                                    @endif
                                </p>
                            </button>
                            <button data-delete-plan="{{ $plan->id }}" class="p-1.5 rounded-lg text-muted-fg hover:text-accent hover:bg-accent/10 transition shrink-0">
                                <x-icon.trash class="w-4 h-4"/>
                            </button>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <x-icon.navigation class="w-10 h-10 text-muted-fg mx-auto mb-3"/>
                            <p class="text-sm text-muted-fg">Belum ada rencana rute.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="plans-data">{!! $plans->map(fn ($p) => ['id' => $p->id, 'points_json' => $p->points_json, 'route_geometry_json' => $p->route_geometry_json, 'start_label' => $p->start_label, 'end_label' => $p->end_label, 'distance_km' => $p->distance_km, 'duration_minutes' => $p->duration_minutes])->toJson() !!}</script>
    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}"></script>
    <script src="{{ asset('js/map-plans.js') }}"></script>
</x-app-layout>
```

- [ ] **Step 2: Rewrite map-plans.js**

Replace the full contents of `public/js/map-plans.js`:

```js
(function () {
  const map = window.MuterinMap.init('map');
  map.zoomControl.setPosition('topright'); // keep the +/- control clear of the floating search box (top-left)
  const token = window.MuterinMap.token();
  const saved = JSON.parse(document.getElementById('plans-data').textContent || '[]');

  const $ = (id) => document.getElementById(id);
  const statusEl = $('route-status');
  const infoPanel = $('info-panel'), routePanel = $('route-panel');
  const searchInput = $('search-input'), searchResults = $('search-results');

  // Floating panels must not pass their clicks through to the map.
  [$('info-panel'), $('route-panel'), searchResults, searchInput.closest('div')].forEach((el) => {
    if (el) L.DomEvent.disableClickPropagation(el);
  });

  let start = null, end = null, via = [];      // each: {lat, lng, label}
  let startMarker = null, endMarker = null, viaMarkers = [];
  let routeLine = null, lastRoute = null, requestSeq = 0;
  let pending = null;                          // location shown in info panel

  function setStatus(text, isError) {
    statusEl.textContent = text || '';
    statusEl.classList.toggle('text-accent', !!isError);
    statusEl.classList.toggle('text-muted-fg', !isError);
  }

  function showInfo(loc) {
    pending = loc;
    routePanel.classList.add('hidden');
    $('info-label').textContent = loc.label;
    $('info-coords').textContent = `${loc.lat.toFixed(5)}, ${loc.lng.toFixed(5)}`;
    $('btn-add-via').classList.toggle('hidden', !(start && end));
    infoPanel.classList.remove('hidden');
  }

  function hideInfo() { infoPanel.classList.add('hidden'); pending = null; }

  async function reverseGeocode(lat, lng) {
    const res = await fetch(`/map/geocode/reverse?lat=${lat}&lng=${lng}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error('reverse failed');
    return res.json();
  }

  map.on('click', async (e) => {
    const { lat, lng } = e.latlng;
    $('info-label').textContent = 'Memuat lokasi...';
    $('info-coords').textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    routePanel.classList.add('hidden');
    $('btn-add-via').classList.toggle('hidden', !(start && end));
    infoPanel.classList.remove('hidden');
    try {
      const loc = await reverseGeocode(lat, lng);
      showInfo(loc);
    } catch {
      showInfo({ label: 'Lokasi tanpa nama', lat, lng });
    }
  });

  function markerFor(loc, color) {
    return L.circleMarker([loc.lat, loc.lng], { color, radius: 7, fillOpacity: 1 }).addTo(map);
  }

  $('btn-set-start').addEventListener('click', () => {
    if (!pending) return;
    start = pending;
    if (startMarker) startMarker.remove();
    startMarker = markerFor(start, '#059669');
    hideInfo();
    maybeRoute();
  });

  $('btn-set-end').addEventListener('click', () => {
    if (!pending) return;
    end = pending;
    if (endMarker) endMarker.remove();
    endMarker = markerFor(end, '#DC2626');
    hideInfo();
    maybeRoute();
  });

  $('btn-add-via').addEventListener('click', () => {
    if (!pending || !(start && end)) return;
    via.push(pending);
    viaMarkers.push(markerFor(pending, '#F59E0B'));
    hideInfo();
    maybeRoute();
  });

  function maybeRoute() {
    if (!(start && end)) return;
    const waypoints = [start, ...via, end].map((p) => [p.lat, p.lng]);
    computeRoute(waypoints);
  }

  function computeRoute(waypoints) {
    const seq = ++requestSeq;
    setStatus('Menghitung rute...', false);
    fetch('/map/route', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ waypoints }),
    })
      .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (seq !== requestSeq) return;
        if (!ok) {
          setStatus(body.error || 'Gagal menghitung rute jalan. Coba lagi sebentar.', true);
          lastRoute = null;
          return;
        }
        lastRoute = body;
        if (routeLine) routeLine.setLatLngs(body.geometry);
        else routeLine = L.polyline(body.geometry, { color: '#0F766E', weight: 5 }).addTo(map);
        setStatus('', false);
        showRoutePanel(body);
      })
      .catch(() => {
        if (seq !== requestSeq) return;
        setStatus('Gagal menghitung rute jalan. Coba lagi sebentar.', true);
        lastRoute = null;
      });
  }

  function fmtDuration(min) {
    if (min < 60) return `${min} menit`;
    const h = Math.floor(min / 60), m = min % 60;
    return m ? `${h} jam ${m} menit` : `${h} jam`;
  }

  function showRoutePanel(route) {
    hideInfo();
    $('route-start-label').textContent = start.label;
    $('route-end-label').textContent = end.label;
    $('route-distance').textContent = `${route.distance_km} km`;
    $('route-duration').textContent = fmtDuration(route.duration_minutes);
    routePanel.classList.remove('hidden');
  }

  // --- Search ---
  function runSearch() {
    const q = searchInput.value.trim();
    if (q.length < 2) return;
    const c = map.getCenter();
    setStatus('Mencari...', false);
    fetch(`/map/geocode/search?q=${encodeURIComponent(q)}&lat=${c.lat}&lng=${c.lng}`, { headers: { Accept: 'application/json' } })
      .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        setStatus('', false);
        if (!ok) { setStatus(body.error || 'Gagal mencari lokasi. Coba lagi sebentar.', true); return; }
        renderResults(body.results || []);
      })
      .catch(() => setStatus('Gagal mencari lokasi. Coba lagi sebentar.', true));
  }

  function renderResults(results) {
    searchResults.innerHTML = '';
    if (!results.length) {
      searchResults.innerHTML = '<p class="px-3 py-2.5 text-sm text-muted-fg">Tidak ada hasil.</p>';
      searchResults.classList.remove('hidden');
      return;
    }
    results.forEach((loc) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'block w-full text-left px-3 py-2.5 text-sm text-foreground hover:bg-muted transition';
      btn.textContent = loc.label;
      btn.addEventListener('click', () => {
        searchResults.classList.add('hidden');
        searchInput.value = loc.label;
        map.flyTo([loc.lat, loc.lng], 15);
        showInfo(loc);
      });
      searchResults.appendChild(btn);
    });
    searchResults.classList.remove('hidden');
  }

  $('search-btn').addEventListener('click', runSearch);
  searchInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); runSearch(); } });

  // --- Save / Reset ---
  $('save-plan').addEventListener('click', async () => {
    if (!lastRoute) { await window.MuterinDialog.alert('Tentukan titik awal & tujuan dulu, tunggu rute selesai dihitung.'); return; }
    const name = await window.MuterinDialog.prompt('Nama rencana rute?', { label: 'Nama', placeholder: 'mis. Rumah ke Kantor' });
    if (!name) return;
    const waypoints = [start, ...via, end].map((p) => [p.lat, p.lng]);
    fetch('/map/plans', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({
        name,
        points: waypoints,
        route_geometry: lastRoute.geometry,
        distance_km: lastRoute.distance_km,
        duration_minutes: lastRoute.duration_minutes,
        start_label: start.label,
        end_label: end.label,
      }),
    }).then(() => location.reload());
  });

  $('reset-plan').addEventListener('click', () => location.reload());

  // --- Saved plans preview ---
  document.querySelectorAll('[data-view-plan]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const plan = saved.find((p) => String(p.id) === btn.dataset.viewPlan);
      if (!plan) return;
      const pts = plan.route_geometry_json || plan.points_json;
      if (routeLine) routeLine.remove();
      routeLine = L.polyline(pts, { color: '#EF4444', weight: 4, dashArray: '6 6' }).addTo(map);
      window.MuterinMap.fitTo(map, pts);
      hideInfo();
      $('route-start-label').textContent = plan.start_label || 'Titik Awal';
      $('route-end-label').textContent = plan.end_label || 'Titik Tujuan';
      $('route-distance').textContent = plan.distance_km ? `${plan.distance_km} km` : '';
      $('route-duration').textContent = plan.duration_minutes ? fmtDuration(plan.duration_minutes) : '';
      routePanel.classList.remove('hidden');
    });
  });

  document.querySelectorAll('[data-delete-plan]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const ok = await window.MuterinDialog.confirm('Hapus rencana ini?', { danger: true, confirmText: 'Hapus' });
      if (!ok) return;
      fetch(`/map/plans/${btn.dataset.deletePlan}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      }).then(() => location.reload());
    });
  });
})();
```

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test`
Expected: all pass (this task is frontend-only  it consumes endpoints already tested in Tasks 2 & 3; this step just confirms nothing broke).

- [ ] **Step 4: Commit**

```bash
git add resources/views/map/plans.blade.php public/js/map-plans.js
git commit -m "feat: Google-Maps-style route planner  search, click-to-info, set start/dest/via, route summary"
```

---

### Task 5: Roll MuterinDialog into Titik Saya + BBM

**Files:**
- Modify: `public/js/map-pins.js`
- Modify: `resources/views/bbm/index.blade.php`

**Interfaces:**
- Consumes: `window.MuterinDialog` (Task 1).

- [ ] **Step 1: Rewrite map-pins.js to use MuterinDialog**

Replace the full contents of `public/js/map-pins.js`:

```js
(function () {
  const map = window.MuterinMap.init('map');
  const token = window.MuterinMap.token();
  const catInput = document.getElementById('pin-category');

  fetch('/map/data', { headers: { Accept: 'application/json' } })
    .then((r) => r.json())
    .then((d) => {
      const pts = [];
      d.pins.forEach((p) => {
        L.circleMarker([p.lat, p.lng], {
          color: window.MuterinMap.categoryColor(p.category), fillOpacity: 0.7, radius: 8,
        }).bindPopup(`<b>${p.title}</b><br>${p.note ?? ''}`).addTo(map);
        pts.push([p.lat, p.lng]);
      });
      window.MuterinMap.fitTo(map, pts);
    });

  map.on('click', async (e) => {
    const category = catInput.value;
    const result = await window.MuterinDialog.prompt('Judul titik?', {
      label: 'Judul',
      placeholder: 'mis. Jalan rusak',
      extra: { label: 'Catatan (opsional)', placeholder: 'Keterangan tambahan…' },
    });
    if (!result) return; // cancelled
    fetch('/map/pins', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ category, lat: e.latlng.lat, lng: e.latlng.lng, title: result.value, note: result.extra || null }),
    }).then(() => location.reload());
  });

  document.querySelectorAll('[data-delete-pin]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const ok = await window.MuterinDialog.confirm('Hapus titik ini?', { danger: true, confirmText: 'Hapus' });
      if (!ok) return;
      fetch(`/map/pins/${btn.dataset.deletePin}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      }).then(() => location.reload());
    });
  });
})();
```

- [ ] **Step 2: Replace the native confirm in the BBM delete form**

In `resources/views/bbm/index.blade.php`, find:

```blade
                                    <form method="POST" action="{{ route('bbm.destroy', $log) }}" onsubmit="return confirm('Hapus catatan ini?')">
```

Replace with:

```blade
                                    <form method="POST" action="{{ route('bbm.destroy', $log) }}" data-confirm="Hapus catatan ini?">
```

Then, immediately before the closing `</x-app-layout>` tag at the very end of `resources/views/bbm/index.blade.php`, add:

```blade
    <script>
        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                if (form.dataset.confirmed === 'yes') return;
                e.preventDefault();
                window.MuterinDialog.confirm(form.dataset.confirm, { danger: true, confirmText: 'Hapus' }).then((ok) => {
                    if (ok) { form.dataset.confirmed = 'yes'; form.submit(); }
                });
            });
        });
    </script>
```

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test`
Expected: all pass (frontend-only changes).

- [ ] **Step 4: Commit**

```bash
git add public/js/map-pins.js resources/views/bbm/index.blade.php
git commit -m "feat: replace native prompt/confirm with MuterinDialog in Titik Saya and BBM"
```

---

### Task 6: Final verification

**Files:** None (verification only).

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: all tests pass. If any fail, fix before proceeding.

- [ ] **Step 2: Manual end-to-end browser verification (controller does this personally  needs real browser + live ORS)**

- `/peta/rencana`:
  - Type a place name in the search box → Enter → results dropdown appears → click a result → map flies there and the info panel shows the location's label.
  - Click anywhere on the map → info panel shows "Memuat lokasi..." then the reverse-geocoded address.
  - Click "Jadikan Titik Awal" (green marker) then click elsewhere → "Jadikan Titik Tujuan" (red marker) → a road-following route draws and the route summary panel appears with start label, end label, distance (km), duration (jam/menit).
  - With start+end set, click a third point → "Tambah Titik Singgah" appears → adds a yellow marker and the route recomputes through all three in order.
  - "Simpan" → styled MuterinDialog prompt (not the browser's grey popup) → save → the saved list shows the plan with its distance/duration. Click the saved plan → route redraws and summary shows the saved start/end labels (no new ORS call).
  - "Hapus" a saved plan → styled MuterinDialog confirm (red button).
- `/peta/titik`: click the map → single styled dialog with title + optional note (not two grey popups) → pin saved. Delete a pin → styled confirm.
- `/bbm`: delete a fuel log → styled confirm (not the grey browser popup) → row removed.

- [ ] **Step 3: Report**

No commit for this task. If manual verification finds any issue, fix it in the relevant task's files, re-run the full suite, and commit the fix describing what was found.

---
