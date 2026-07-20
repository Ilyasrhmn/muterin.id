# Real-Time Riding Map + Road-Following Route Planner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a live map to the Riding (trip recording) page showing current position and the path traveled so far, and replace Peta Rencana's straight-line route drawing with real road-following routes computed by OpenRouteService.

**Architecture:** A new `RouteService` proxies OpenRouteService's Directions API server-side (API key never reaches the browser). A new `POST /map/route` endpoint exposes it for live preview while planning. `route_plans` gains 3 nullable columns to persist the computed route so viewing a saved plan never re-calls the external API. The Riding page's existing real-time GPS tracking (`watchPosition`) is unchanged — a Leaflet map is layered on top, updated from the same position callback that already drives the distance counter.

**Tech Stack:** Laravel 13, Leaflet.js (already used elsewhere in the app, loaded via CDN), OpenRouteService Directions API (`cycling-regular` profile), Laravel's `Http` facade.

## Global Constraints

- API key: `ORS_API_KEY` is already set in `.env` (not committed — confirmed gitignored). `.env.example` already documents the empty key. Do not touch either file.
- OpenRouteService coordinate order is `[lng, lat]` — the opposite of the `[lat, lng]` convention used everywhere else in this codebase (`Trip::path_json`, `RoutePlan::points_json`, Leaflet's own `L.latLng`). `RouteService` is the only place that does this conversion — every other file in this plan works exclusively in `[lat, lng]`.
- Service-class pattern: pure PHP class in `app/Services/`, method-injected into controllers (same pattern as `OdometerService`, `HealthScoreService`, etc. elsewhere in this codebase).
- Error copy (exact, when routing fails): `"Gagal menghitung rute jalan. Coba lagi sebentar."` — do not silently fall back to a straight line.
- `public/js/*.js` files are plain static assets served via `asset()`, not part of the Vite build — editing them takes effect immediately, no `npm run build` needed.
- Commit directly to `master` (no worktree — established convention for this project).
- TDD: write failing tests first, verify RED, then implement, verify GREEN.

---

### Task 1: RouteNotFoundException + RouteService

**Files:**
- Create: `app/Exceptions/RouteNotFoundException.php`
- Create: `app/Services/RouteService.php`
- Modify: `config/services.php`
- Test: `tests/Unit/RouteServiceTest.php`

**Interfaces:**
- Produces: `App\Services\RouteService::route(array $waypoints): array` where `$waypoints` is `[[lat, lng], [lat, lng], ...]` (min 2) and the return is `['geometry' => [[lat, lng], ...], 'distance_km' => float, 'duration_minutes' => int]`. Throws `App\Exceptions\RouteNotFoundException` on any failure (ORS error response, malformed response, HTTP failure).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/RouteServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Exceptions\RouteNotFoundException;
use App\Services\RouteService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteServiceTest extends TestCase
{
    public function test_route_returns_geometry_distance_and_duration(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'features' => [
                    [
                        'geometry' => ['coordinates' => [[106.8, -6.2], [106.81, -6.21], [106.82, -6.22]]],
                        'properties' => ['summary' => ['distance' => 1500.0, 'duration' => 300.0]],
                    ],
                ],
            ], 200),
        ]);

        $service = new RouteService();
        $result = $service->route([[-6.2, 106.8], [-6.22, 106.82]]);

        $this->assertEquals([[-6.2, 106.8], [-6.21, 106.81], [-6.22, 106.82]], $result['geometry']);
        $this->assertEquals(1.5, $result['distance_km']);
        $this->assertEquals(5, $result['duration_minutes']);
    }

    public function test_route_sends_coordinates_to_ors_in_lng_lat_order(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'features' => [
                    [
                        'geometry' => ['coordinates' => [[106.8, -6.2], [106.82, -6.22]]],
                        'properties' => ['summary' => ['distance' => 1000.0, 'duration' => 200.0]],
                    ],
                ],
            ], 200),
        ]);

        $service = new RouteService();
        $service->route([[-6.2, 106.8], [-6.22, 106.82]]);

        Http::assertSent(function ($request) {
            return $request['coordinates'] === [[106.8, -6.2], [106.82, -6.22]];
        });
    }

    public function test_route_throws_when_ors_request_fails(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(['error' => 'no route found'], 404),
        ]);

        $service = new RouteService();

        $this->expectException(RouteNotFoundException::class);
        $service->route([[-6.2, 106.8], [-6.22, 106.82]]);
    }

    public function test_route_throws_when_ors_response_has_no_features(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(['features' => []], 200),
        ]);

        $service = new RouteService();

        $this->expectException(RouteNotFoundException::class);
        $service->route([[-6.2, 106.8], [-6.22, 106.82]]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=RouteServiceTest`
Expected: FAIL — `App\Services\RouteService` and `App\Exceptions\RouteNotFoundException` don't exist yet.

- [ ] **Step 3: Create the exception**

`app/Exceptions/RouteNotFoundException.php`:

```php
<?php

namespace App\Exceptions;

use Exception;

class RouteNotFoundException extends Exception
{
}
```

- [ ] **Step 4: Add ORS config**

In `config/services.php`, find:

```php
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
```

Replace with:

```php
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ors' => [
        'key' => env('ORS_API_KEY'),
    ],

];
```

- [ ] **Step 5: Create RouteService**

`app/Services/RouteService.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\RouteNotFoundException;
use Illuminate\Support\Facades\Http;

class RouteService
{
    private const ERROR_MESSAGE = 'Gagal menghitung rute jalan. Coba lagi sebentar.';

    public function route(array $waypoints): array
    {
        $coordinates = array_map(fn ($point) => [$point[1], $point[0]], $waypoints);

        $response = Http::withHeaders([
            'Authorization' => config('services.ors.key'),
        ])->timeout(8)->post('https://api.openrouteservice.org/v2/directions/cycling-regular/geojson', [
            'coordinates' => $coordinates,
        ]);

        if ($response->failed()) {
            throw new RouteNotFoundException(self::ERROR_MESSAGE);
        }

        $feature = $response->json('features.0');

        if (!$feature) {
            throw new RouteNotFoundException(self::ERROR_MESSAGE);
        }

        $geometry = array_map(fn ($coord) => [$coord[1], $coord[0]], $feature['geometry']['coordinates']);
        $summary = $feature['properties']['summary'];

        return [
            'geometry' => $geometry,
            'distance_km' => round($summary['distance'] / 1000, 2),
            'duration_minutes' => (int) round($summary['duration'] / 60),
        ];
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=RouteServiceTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Exceptions/RouteNotFoundException.php app/Services/RouteService.php config/services.php tests/Unit/RouteServiceTest.php
git commit -m "feat: RouteService — OpenRouteService directions proxy"
```

---

### Task 2: POST /map/route endpoint

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/MapController.php`
- Test: `tests/Feature/MapTest.php`

**Interfaces:**
- Consumes: `App\Services\RouteService::route()` (Task 1).
- Produces: route `map.route` (POST `/map/route`), accepts `{"waypoints": [[lat,lng], ...]}`, returns the same shape as `RouteService::route()` on success (200) or `{"error": "..."}` on failure (422).

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/MapTest.php`, add `use Illuminate\Support\Facades\Http;` to the imports, then add to the class:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MapTest`
Expected: FAIL — route `map.route` / `/map/route` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, find:

```php
    Route::get('map/data', [MapController::class, 'data'])->name('map.data');
```

Replace with:

```php
    Route::get('map/data', [MapController::class, 'data'])->name('map.data');
    Route::post('map/route', [MapController::class, 'previewRoute'])->name('map.route');
```

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/MapController.php`, add these imports at the top (alongside the existing `use` lines):

```php
use App\Exceptions\RouteNotFoundException;
use App\Services\RouteService;
```

Then add this method (anywhere among the other public methods, e.g. right after `data()`):

```php
    public function previewRoute(Request $request, RouteService $routing)
    {
        $data = $request->validate([
            'waypoints' => 'required|array|min:2',
            'waypoints.*' => 'required|array|size:2',
            'waypoints.*.0' => 'required|numeric|between:-90,90',
            'waypoints.*.1' => 'required|numeric|between:-180,180',
        ]);

        try {
            return response()->json($routing->route($data['waypoints']));
        } catch (RouteNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=MapTest`
Expected: PASS (8 tests — 4 pre-existing + 4 new)

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/MapController.php tests/Feature/MapTest.php
git commit -m "feat: POST /map/route endpoint for live route preview"
```

---

### Task 3: route_plans schema + storePlan() persists geometry

**Files:**
- Create: `database/migrations/2026_07_21_100000_add_route_geometry_to_route_plans_table.php`
- Modify: `app/Models/RoutePlan.php`
- Modify: `app/Http/Controllers/MapController.php`
- Test: `tests/Feature/MapTest.php`

**Interfaces:**
- Consumes: nothing new from earlier tasks (this task's frontend counterpart, Task 4, is what will actually call `/map/route` before hitting `storePlan()`).
- Produces: `route_plans` table gains `route_geometry_json` (nullable json), `distance_km` (nullable decimal), `duration_minutes` (nullable unsigned int). `RoutePlan` model exposes these as fillable, `route_geometry_json` cast to `array`.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/MapTest.php`, add:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MapTest`
Expected: FAIL — `route_geometry_json`/`distance_km`/`duration_minutes` columns don't exist and aren't validated/stored yet, so the assertion against `route_plans` fails (the row is created but without these values, or the request 500s on a missing column depending on validation — either way, not a clean pass).

- [ ] **Step 3: Create the migration**

`database/migrations/2026_07_21_100000_add_route_geometry_to_route_plans_table.php`:

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
            $table->json('route_geometry_json')->nullable()->after('points_json');
            $table->decimal('distance_km', 8, 2)->nullable()->after('route_geometry_json');
            $table->unsignedInteger('duration_minutes')->nullable()->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->dropColumn(['route_geometry_json', 'distance_km', 'duration_minutes']);
        });
    }
};
```

- [ ] **Step 4: Update the RoutePlan model**

In `app/Models/RoutePlan.php`, find:

```php
    protected $fillable = ['user_id', 'name', 'points_json'];

    protected $casts = ['points_json' => 'array'];
```

Replace with:

```php
    protected $fillable = ['user_id', 'name', 'points_json', 'route_geometry_json', 'distance_km', 'duration_minutes'];

    protected $casts = ['points_json' => 'array', 'route_geometry_json' => 'array'];
```

- [ ] **Step 5: Update storePlan()**

In `app/Http/Controllers/MapController.php`, find:

```php
    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'points' => 'required|array|min:2',
        ]);
        $plan = auth()->user()->routePlans()->create([
            'name' => $data['name'],
            'points_json' => $data['points'],
        ]);

        return response()->json($plan, 201);
    }
```

Replace with:

```php
    public function storePlan(Request $request)
    {
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

        return response()->json($plan, 201);
    }
```

- [ ] **Step 6: Run migration and tests**

Run: `php artisan migrate --no-interaction && php artisan test --filter=MapTest`
Expected: PASS (9 tests — including the pre-existing `test_saving_route_plan_requires_at_least_two_points`, which still passes since sending only 1 point still fails the `points` `min:2` rule regardless of the new required fields).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_21_100000_add_route_geometry_to_route_plans_table.php app/Models/RoutePlan.php app/Http/Controllers/MapController.php tests/Feature/MapTest.php
git commit -m "feat: route_plans stores computed road geometry, distance, and duration"
```

---

### Task 4: Peta Rencana — road-following planner UI

**Files:**
- Modify: `public/js/map-plans.js`
- Modify: `resources/views/map/plans.blade.php`

**Interfaces:**
- Consumes: `POST /map/route` (Task 2), `POST /map/plans` now requiring `route_geometry`/`distance_km`/`duration_minutes` (Task 3), `window.AmictaMap` helpers (`init`, `token`, `fitTo`) already in `public/js/map-common.js`.

- [ ] **Step 1: Replace map-plans.js**

Replace the full contents of `public/js/map-plans.js`:

```js
(function () {
  const map = window.AmictaMap.init('map');
  const token = window.AmictaMap.token();
  let points = [];
  let markers = [];
  let routeLine = null;
  let lastRoute = null;

  const saved = JSON.parse(document.getElementById('plans-data').textContent || '[]');
  const statusEl = document.getElementById('route-status');

  function setStatus(text, isError) {
    statusEl.textContent = text || '';
    statusEl.classList.toggle('text-accent', !!isError);
    statusEl.classList.toggle('text-muted-fg', !isError);
  }

  function computeRoute() {
    if (points.length < 2) return;
    setStatus('Menghitung rute...', false);
    fetch('/map/route', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ waypoints: points }),
    })
      .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (!ok) {
          setStatus(body.error || 'Gagal menghitung rute jalan. Coba lagi sebentar.', true);
          lastRoute = null;
          return;
        }
        lastRoute = body;
        if (routeLine) routeLine.setLatLngs(body.geometry);
        else routeLine = L.polyline(body.geometry, { color: '#0F766E', weight: 4 }).addTo(map);
        setStatus(`${body.distance_km} km · ${body.duration_minutes} menit`, false);
      })
      .catch(() => {
        setStatus('Gagal menghitung rute jalan. Coba lagi sebentar.', true);
        lastRoute = null;
      });
  }

  map.on('click', (e) => {
    const p = [e.latlng.lat, e.latlng.lng];
    points.push(p);
    const isFirst = points.length === 1;
    const marker = L.circleMarker(p, { color: isFirst ? '#059669' : '#DC2626', radius: 6, fillOpacity: 1 }).addTo(map);
    markers.push(marker);
    computeRoute();
  });

  document.getElementById('save-plan').addEventListener('click', () => {
    if (!lastRoute) { alert('Klik minimal 2 titik di peta dan tunggu rute selesai dihitung dulu.'); return; }
    const name = prompt('Nama rencana rute?');
    if (!name) return;
    fetch('/map/plans', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({
        name,
        points,
        route_geometry: lastRoute.geometry,
        distance_km: lastRoute.distance_km,
        duration_minutes: lastRoute.duration_minutes,
      }),
    }).then(() => location.reload());
  });

  document.getElementById('reset-plan').addEventListener('click', () => location.reload());

  document.querySelectorAll('[data-view-plan]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const plan = saved.find((p) => String(p.id) === btn.dataset.viewPlan);
      if (!plan) return;
      const pts = plan.route_geometry_json || plan.points_json;
      L.polyline(pts, { color: '#EF4444', weight: 4, dashArray: '6 6' }).addTo(map);
      window.AmictaMap.fitTo(map, pts);
    });
  });

  document.querySelectorAll('[data-delete-plan]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!confirm('Hapus rencana ini?')) return;
      fetch(`/map/plans/${btn.dataset.deletePlan}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      }).then(() => location.reload());
    });
  });
})();
```

- [ ] **Step 2: Update the plans page toolbar and status element**

In `resources/views/map/plans.blade.php`, find:

```blade
                <div class="bg-surface border border-border rounded-2xl p-4 flex flex-wrap items-center gap-3">
                    <p class="text-sm text-muted-fg">Klik titik-titik di peta untuk menyusun jalur.</p>
                    <div class="flex items-center gap-2 ms-auto">
                        <x-ui.button id="reset-plan" variant="outline" size="sm" type="button">Reset</x-ui.button>
                        <x-ui.button id="save-plan" variant="primary" size="sm" type="button">Simpan Rencana</x-ui.button>
                    </div>
                </div>
```

Replace with:

```blade
                <div class="bg-surface border border-border rounded-2xl p-4 flex flex-wrap items-center gap-3">
                    <div>
                        <p class="text-sm text-muted-fg">Klik titik awal, lalu titik tujuan — rute jalan otomatis dihitung.</p>
                        <p id="route-status" class="text-sm text-muted-fg mt-1"></p>
                    </div>
                    <div class="flex items-center gap-2 ms-auto">
                        <x-ui.button id="reset-plan" variant="outline" size="sm" type="button">Reset</x-ui.button>
                        <x-ui.button id="save-plan" variant="primary" size="sm" type="button">Simpan Rencana</x-ui.button>
                    </div>
                </div>
```

- [ ] **Step 3: Show distance/duration in the saved plans list**

In `resources/views/map/plans.blade.php`, find:

```blade
                            <button data-view-plan="{{ $plan->id }}" class="min-w-0 text-left flex-1">
                                <p class="font-bold text-sm text-foreground truncate">{{ $plan->name }}</p>
                                <p class="text-[11px] text-muted-fg mt-0.5">{{ count($plan->points_json) }} titik</p>
                            </button>
```

Replace with:

```blade
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
```

- [ ] **Step 4: Pass route_geometry_json to the frontend**

In `resources/views/map/plans.blade.php`, find:

```blade
    <script type="application/json" id="plans-data">{!! $plans->map(fn ($p) => ['id' => $p->id, 'points_json' => $p->points_json])->toJson() !!}</script>
```

Replace with:

```blade
    <script type="application/json" id="plans-data">{!! $plans->map(fn ($p) => ['id' => $p->id, 'points_json' => $p->points_json, 'route_geometry_json' => $p->route_geometry_json])->toJson() !!}</script>
```

- [ ] **Step 5: Run the full test suite**

Run: `php artisan test`
Expected: all pass (this task has no new backend logic — it consumes what Tasks 1-3 already built and tested — so this step just confirms nothing broke).

- [ ] **Step 6: Commit**

```bash
git add public/js/map-plans.js resources/views/map/plans.blade.php
git commit -m "feat: Peta Rencana draws real road-following routes via OpenRouteService"
```

---

### Task 5: Riding page — live map

**Files:**
- Modify: `resources/views/riding/index.blade.php`
- Modify: `public/js/trip-recorder.js`

**Interfaces:**
- Consumes: `window.AmictaMap.init()` (already in `public/js/map-common.js`, already loaded on other map pages — this task is the first to load it on the Riding page).

- [ ] **Step 1: Add the map and Leaflet includes to the Riding page**

In `resources/views/riding/index.blade.php`, find:

```blade
<x-app-layout>
    <x-slot name="header">Riding</x-slot>
    <div class="max-w-lg mx-auto p-4 sm:p-6 lg:p-8" id="riding-app">
```

Replace with:

```blade
<x-app-layout>
    <x-slot name="header">Riding</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="max-w-lg mx-auto p-4 sm:p-6 lg:p-8" id="riding-app">
```

Then find:

```blade
                <div class="text-center py-10 rounded-token bg-muted">
                    <p class="text-6xl font-heading font-bold text-primary tabular-nums"><span id="distance">0.00</span></p>
```

Replace with:

```blade
                <div id="ride-map" class="rounded-token overflow-hidden border border-border" style="height: 40vh"></div>

                <div class="text-center py-10 rounded-token bg-muted">
                    <p class="text-6xl font-heading font-bold text-primary tabular-nums"><span id="distance">0.00</span></p>
```

Then find:

```blade
    @csrf
    @if ($motorcycles->isNotEmpty())
        <script src="{{ asset('js/trip-recorder.js') }}"></script>
    @endif
</x-app-layout>
```

Replace with:

```blade
    @csrf
    @if ($motorcycles->isNotEmpty())
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/map-common.js') }}"></script>
        <script src="{{ asset('js/trip-recorder.js') }}"></script>
    @endif
</x-app-layout>
```

- [ ] **Step 2: Add live map updates to trip-recorder.js**

Replace the full contents of `public/js/trip-recorder.js`:

```js
(function () {
  const $ = (id) => document.getElementById(id);
  const startBtn = $('start-btn'), stopBtn = $('stop-btn');
  const IDLE_MS = 5 * 60 * 1000;        // ponytail: idle auto-stop 5 menit, tuning di device asli
  const MAX_JUMP_KM = 1;                // ponytail: buang lonjakan >1km antar update (outlier GPS)

  let watchId = null, last = null, distance = 0, startTs = 0, path = [], idleTimer = null, tick = null;
  let marker = null, liveLine = null;

  const map = window.AmictaMap.init('ride-map');
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((pos) => {
      map.setView([pos.coords.latitude, pos.coords.longitude], 15);
    });
  }

  function haversine(a, b) {
    const R = 6371, toRad = (d) => d * Math.PI / 180;
    const dLat = toRad(b[0] - a[0]), dLng = toRad(b[1] - a[1]);
    const s = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(s), Math.sqrt(1 - s));
  }

  function fmtDur(sec) {
    const m = String(Math.floor(sec / 60)).padStart(2, '0'), s = String(sec % 60).padStart(2, '0');
    return `${m}:${s}`;
  }

  function onPos(pos) {
    const p = [pos.coords.latitude, pos.coords.longitude];
    if (last) {
      const d = haversine(last, p);
      if (d <= MAX_JUMP_KM) {
        distance += d;
        $('distance').textContent = distance.toFixed(2);
      }
    }
    last = p;
    path.push(p);
    if (marker) marker.setLatLng(p);
    else marker = L.circleMarker(p, { color: '#0F766E', radius: 7, fillOpacity: 1 }).addTo(map);
    if (liveLine) liveLine.addLatLng(p);
    else liveLine = L.polyline([p], { color: '#0F766E', weight: 4 }).addTo(map);
    map.panTo(p);
    resetIdle();
  }

  function resetIdle() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(stop, IDLE_MS);
  }

  function start() {
    if (!navigator.geolocation) {
      $('gps-msg').textContent = 'Browser tidak mendukung GPS.';
      return;
    }
    navigator.geolocation.getCurrentPosition(() => {
      distance = 0;
      last = null;
      path = [];
      startTs = Date.now();
      $('distance').textContent = '0.00';
      if (liveLine) { liveLine.remove(); liveLine = null; }
      if (marker) { marker.remove(); marker = null; }
      watchId = navigator.geolocation.watchPosition(onPos, onErr, { enableHighAccuracy: true, maximumAge: 0 });
      tick = setInterval(() => {
        $('duration').textContent = fmtDur(Math.floor((Date.now() - startTs) / 1000));
      }, 1000);
      startBtn.classList.add('hidden');
      stopBtn.classList.remove('hidden');
      resetIdle();
    }, onErr, { enableHighAccuracy: true });
  }

  function onErr() {
    $('gps-msg').textContent = 'Izin GPS ditolak atau tidak tersedia.';
  }

  async function stop() {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    clearInterval(tick);
    clearTimeout(idleTimer);
    watchId = null;
    const duration = Math.floor((Date.now() - startTs) / 1000);
    const token = document.querySelector('input[name="_token"]').value;
    await fetch('/trips', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: JSON.stringify({
        motorcycle_id: $('motor-select').value,
        distance_km: Number(distance.toFixed(2)),
        duration_seconds: duration,
        path,
      }),
    });
    window.location.href = '/dashboard';
  }

  startBtn.addEventListener('click', start);
  stopBtn.addEventListener('click', stop);
})();
```

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test`
Expected: all pass (this task is frontend-only — the distance/duration/haversine logic that IS tested indirectly via `TripControllerTest`/`TripTest` is untouched; only map rendering was added around it).

- [ ] **Step 4: Manual verification**

Skip actual browser interaction in this step if running in a sandboxed subagent without browser tooling or GPS access — note this as a concern in the report instead of skipping silently. If browser tooling is available: open `/riding`, confirm the map appears immediately (even before pressing "Mulai"), confirm pressing "Mulai Perjalanan" and simulating GPS position changes (browser devtools geolocation override) makes the marker move, the blue line grow, and the map follow the marker.

- [ ] **Step 5: Commit**

```bash
git add resources/views/riding/index.blade.php public/js/trip-recorder.js
git commit -m "feat: live map on Riding page shows current position and path traveled"
```

---

### Task 6: Final verification

**Files:** None (verification only).

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: all tests pass (92 pre-existing + this plan's new tests). If any fail, fix before proceeding.

- [ ] **Step 2: Manual end-to-end browser verification**

This step requires real browser tooling and should be performed directly by the controller (not delegated to a sandboxed implementer subagent, which typically lacks browser access):

- `/riding` — map appears on load; start a trip, confirm distance/duration still work exactly as before, confirm the map now shows a live marker and growing path line.
- `/peta/rencana` — click a start point (green marker), click a destination point (red marker), confirm a road-following route (not a straight line) is drawn within a couple seconds, confirm the status text shows distance/duration. Click a third point, confirm the route recalculates through all three points in order. Save the plan, reload the page, click it in the saved list, confirm the same road-following route redraws instantly (no new network delay, since it's stored, not recomputed).
- Try clicking 2 points somewhere a route genuinely can't be found (e.g., across open ocean) if easy to test, or simulate a failure — confirm the error message appears and no straight-line fallback is silently drawn.

- [ ] **Step 3: Report**

No commit needed for this task — it's verification-only. If manual verification finds any issue, fix it in the relevant task's files, re-run the full suite, and commit the fix with a message describing what was found and fixed.

---
