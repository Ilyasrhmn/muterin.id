# Real-Time Trip Persistence Implementation Plan (Plan 2 of 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make riding trips survive a mid-ride crash  create a draft trip on start, auto-save (checkpoint) the growing path every ~10 seconds during the ride, finalize on stop (updating the odometer exactly once), and recover any unfinished draft next time the Riding page loads.

**Architecture:** Trips gain a `status` (`recording` | `completed`). The old single `POST /trips` (save-everything-at-the-end) is replaced by a start → checkpoint → finish lifecycle. The odometer is only ever touched at `finish`, guarded by `status` so it can never double-count. Peta Rute shows only `completed` trips. The Riding page detects an interrupted `recording` trip and offers to finish or discard it.

**Tech Stack:** Laravel 13, Leaflet (CDN, already used), vanilla JS, `OdometerService` (existing), `MuterinDialog` (from Plan 1).

## Global Constraints

- **Depends on Plan 1** (`2026-07-21-maps-planner-google-style.md`) being complete: the recovery banner's "Buang" confirmation uses `window.MuterinDialog.confirm`, which Plan 1 makes globally available. Execute Plan 1 first.
- The odometer must be updated **exactly once per trip**, only at `finish`, and never at `start` or `checkpoint`. `finish` guards on `status`: a trip already `completed` must not update the odometer again (idempotent).
- Existing GPS/Haversine/distance logic in `public/js/trip-recorder.js` must NOT change  only the persistence wiring (which endpoint is called, and when) changes.
- Peta Rute (`MapController::routesPage` + `data`) must show only `status='completed'` trips.
- `public/js/*.js` are static assets (no Vite build needed).
- Commit directly to `master` (no worktree  established convention).
- TDD for backend (RED→GREEN). The `trip-recorder.js` rewrite and the recovery banner UI are verified manually in the browser by the controller in the final task.
- Checkpoint interval is a `ponytail:` tuning knob (10s), not a hard law.

---

### Task 1: trips.status column + completed-only filters

**Files:**
- Create: `database/migrations/2026_07_21_120000_add_status_to_trips_table.php`
- Modify: `app/Models/Trip.php`
- Modify: `app/Http/Controllers/MapController.php`
- Test: `tests/Feature/MapTest.php`

**Interfaces:**
- Produces: `trips.status` enum (`recording`|`completed`), default `completed`. `Trip` model exposes `status` as fillable. Peta Rute (`routesPage` and `data`) filter `status='completed'`.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/MapTest.php`, add (`use App\Models\Motorcycle;` and `use App\Models\Trip;`  add these imports at the top if not already present):

```php
    public function test_map_data_excludes_recording_trips(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A']);
        $motor->trips()->create([
            'distance_km' => 5, 'duration_seconds' => 300, 'path_json' => [[-6.2, 106.8]],
            'status' => 'completed', 'ended_at' => now(),
        ]);
        $motor->trips()->create([
            'distance_km' => 1, 'duration_seconds' => 60, 'path_json' => [[-6.3, 106.9]],
            'status' => 'recording',
        ]);

        $data = $this->actingAs($user)->getJson('/map/data')->json();

        $this->assertCount(1, $data['trips']);
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=MapTest`
Expected: FAIL  `status` column doesn't exist (mass-assign error / unknown column), and `data` doesn't filter it.

- [ ] **Step 3: Create the migration**

`database/migrations/2026_07_21_120000_add_status_to_trips_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->enum('status', ['recording', 'completed'])->default('completed')->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
```

- [ ] **Step 4: Update the Trip model**

In `app/Models/Trip.php`, find:

```php
    protected $fillable = ['motorcycle_id', 'distance_km', 'duration_seconds', 'path_json', 'started_at', 'ended_at'];
```

Replace with:

```php
    protected $fillable = ['motorcycle_id', 'distance_km', 'duration_seconds', 'status', 'path_json', 'started_at', 'ended_at'];
```

- [ ] **Step 5: Filter completed-only in MapController**

In `app/Http/Controllers/MapController.php`, find (in `routesPage()`):

```php
        $trips = Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', auth()->id()))
            ->whereNotNull('path_json')
            ->with('motorcycle')
            ->latest('ended_at')
            ->get();
```

Replace with:

```php
        $trips = Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', auth()->id()))
            ->where('status', 'completed')
            ->whereNotNull('path_json')
            ->with('motorcycle')
            ->latest('ended_at')
            ->get();
```

Then find (in `data()`):

```php
            'trips' => Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))
                ->whereNotNull('path_json')->get(['id', 'path_json']),
```

Replace with:

```php
            'trips' => Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))
                ->where('status', 'completed')
                ->whereNotNull('path_json')->get(['id', 'path_json']),
```

- [ ] **Step 6: Migrate and test**

Run: `php artisan migrate --no-interaction && php artisan test --filter=MapTest`
Expected: PASS (all MapTest, including the new recording-exclusion test)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_21_120000_add_status_to_trips_table.php app/Models/Trip.php app/Http/Controllers/MapController.php tests/Feature/MapTest.php
git commit -m "feat: trips.status column; Peta Rute shows only completed trips"
```

---

### Task 2: Trip start / checkpoint / finish / destroy lifecycle

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/TripController.php`
- Test: `tests/Feature/TripTest.php`

**Interfaces:**
- Consumes: `App\Services\OdometerService::record()` (existing), `trips.status` (Task 1).
- Produces: routes `trips.start` (POST `/trips/start`), `trips.checkpoint` (PATCH `/trips/{trip}/checkpoint`), `trips.finish` (PATCH `/trips/{trip}/finish`), `trips.destroy` (DELETE `/trips/{trip}`). The old `POST /trips` (`trips.store`) is removed.

- [ ] **Step 1: Rewrite TripTest for the new lifecycle**

Replace the full contents of `tests/Feature/TripTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    private function motor(User $user): Motorcycle
    {
        return Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 1000, 'current_odometer_km' => 1000,
        ]);
    }

    public function test_start_creates_recording_draft_without_touching_odometer(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);

        $tripId = $this->actingAs($user)->postJson(route('trips.start'), [
            'motorcycle_id' => $motor->id,
        ])->assertCreated()->json('trip_id');

        $this->assertDatabaseHas('trips', ['id' => $tripId, 'status' => 'recording']);
        $this->assertEquals(1000, $motor->fresh()->current_odometer_km);
    }

    public function test_checkpoint_updates_draft_without_touching_odometer(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 0, 'duration_seconds' => 0, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->patchJson(route('trips.checkpoint', $trip), [
            'distance_km' => 4.2, 'duration_seconds' => 300, 'path' => [[-6.2, 106.8], [-6.21, 106.81]],
        ])->assertOk();

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'distance_km' => 4.2, 'status' => 'recording']);
        $this->assertEquals(1000, $motor->fresh()->current_odometer_km);
    }

    public function test_finish_completes_trip_and_increments_odometer_once(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 0, 'duration_seconds' => 0, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->patchJson(route('trips.finish', $trip), [
            'distance_km' => 12.5, 'duration_seconds' => 1800, 'path' => [[-6.2, 106.8], [-6.21, 106.81]],
        ])->assertOk();

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'completed', 'distance_km' => 12.5]);
        $this->assertEquals(1013, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 1013, 'source' => 'trip']);
    }

    public function test_finish_is_idempotent_for_odometer(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 0, 'duration_seconds' => 0, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->patchJson(route('trips.finish', $trip), ['distance_km' => 12.5, 'duration_seconds' => 1800]);
        $this->actingAs($user)->patchJson(route('trips.finish', $trip), ['distance_km' => 12.5, 'duration_seconds' => 1800])->assertOk();

        // Odometer only moved once (1000 -> 1013), not twice.
        $this->assertEquals(1013, $motor->fresh()->current_odometer_km);
        $this->assertEquals(1, $motor->odometerReadings()->where('source', 'trip')->count());
    }

    public function test_destroy_deletes_a_recording_draft(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 1, 'duration_seconds' => 60, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->deleteJson(route('trips.destroy', $trip))->assertOk();
        $this->assertDatabaseMissing('trips', ['id' => $trip->id]);
    }

    public function test_destroy_refuses_a_completed_trip(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $trip = $motor->trips()->create(['distance_km' => 5, 'duration_seconds' => 300, 'status' => 'completed', 'ended_at' => now()]);

        $this->actingAs($user)->deleteJson(route('trips.destroy', $trip))->assertStatus(422);
        $this->assertDatabaseHas('trips', ['id' => $trip->id]);
    }

    public function test_lifecycle_endpoints_enforce_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherMotor = Motorcycle::create(['user_id' => $other->id, 'nickname' => 'X']);
        $otherTrip = $otherMotor->trips()->create(['distance_km' => 1, 'duration_seconds' => 60, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->postJson(route('trips.start'), ['motorcycle_id' => $otherMotor->id])->assertForbidden();
        $this->actingAs($user)->patchJson(route('trips.checkpoint', $otherTrip), ['distance_km' => 1, 'duration_seconds' => 1])->assertForbidden();
        $this->actingAs($user)->patchJson(route('trips.finish', $otherTrip), ['distance_km' => 1, 'duration_seconds' => 1])->assertForbidden();
        $this->actingAs($user)->deleteJson(route('trips.destroy', $otherTrip))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=TripTest`
Expected: FAIL  `trips.start`/`trips.checkpoint`/`trips.finish`/`trips.destroy` routes/methods don't exist yet.

- [ ] **Step 3: Replace the trips routes**

In `routes/web.php`, find:

```php
    Route::post('trips', [TripController::class, 'store'])->name('trips.store');
```

Replace with:

```php
    Route::post('trips/start', [TripController::class, 'start'])->name('trips.start');
    Route::patch('trips/{trip}/checkpoint', [TripController::class, 'checkpoint'])->name('trips.checkpoint');
    Route::patch('trips/{trip}/finish', [TripController::class, 'finish'])->name('trips.finish');
    Route::delete('trips/{trip}', [TripController::class, 'destroy'])->name('trips.destroy');
```

- [ ] **Step 4: Rewrite TripController**

Replace the full contents of `app/Http/Controllers/TripController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Models\Trip;
use App\Services\OdometerService;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function create()
    {
        $motorcycles = auth()->user()->motorcycles()->get();
        $unfinished = Trip::whereHas('motorcycle', fn ($q) => $q->where('user_id', auth()->id()))
            ->where('status', 'recording')
            ->with('motorcycle')
            ->latest()
            ->first();

        return view('riding.index', compact('motorcycles', 'unfinished'));
    }

    public function start(Request $request)
    {
        $data = $request->validate(['motorcycle_id' => 'required|exists:motorcycles,id']);
        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $trip = $motor->trips()->create([
            'distance_km' => 0,
            'duration_seconds' => 0,
            'status' => 'recording',
            'path_json' => [],
            'started_at' => now(),
        ]);

        return response()->json(['trip_id' => $trip->id], 201);
    }

    public function checkpoint(Request $request, Trip $trip)
    {
        $this->authorizeTrip($trip);
        $data = $this->validatedProgress($request);

        // Only a recording draft accepts checkpoints; ignore silently if already finished.
        if ($trip->status === 'recording') {
            $trip->update([
                'distance_km' => $data['distance_km'],
                'duration_seconds' => $data['duration_seconds'],
                'path_json' => $data['path'] ?? $trip->path_json,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function finish(Request $request, Trip $trip, OdometerService $odometer)
    {
        $this->authorizeTrip($trip);
        $data = $this->validatedProgress($request);

        // Guard: only move the odometer the first time a trip is finished.
        if ($trip->status === 'recording') {
            $trip->update([
                'distance_km' => $data['distance_km'],
                'duration_seconds' => $data['duration_seconds'],
                'path_json' => $data['path'] ?? $trip->path_json,
                'status' => 'completed',
                'ended_at' => now(),
            ]);

            $motor = $trip->motorcycle;
            $newOdometer = $motor->current_odometer_km + (int) round($data['distance_km']);
            $odometer->record($motor, $newOdometer, now(), 'trip');
        }

        return response()->json(['ok' => true, 'trip_id' => $trip->id]);
    }

    public function destroy(Trip $trip)
    {
        $this->authorizeTrip($trip);
        abort_if($trip->status !== 'recording', 422, 'Hanya perjalanan yang belum selesai yang bisa dibuang.');
        $trip->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeTrip(Trip $trip): void
    {
        abort_unless($trip->motorcycle->user_id === auth()->id(), 403);
    }

    private function validatedProgress(Request $request): array
    {
        return $request->validate([
            'distance_km' => 'required|numeric|min:0',
            'duration_seconds' => 'required|integer|min:0',
            'path' => 'nullable|array',
        ]);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=TripTest`
Expected: PASS (7 tests)

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/TripController.php tests/Feature/TripTest.php
git commit -m "feat: trip start/checkpoint/finish/destroy lifecycle with once-only odometer update"
```

---

### Task 3: trip-recorder.js  start/checkpoint/finish wiring

**Files:**
- Modify: `public/js/trip-recorder.js`

**Interfaces:**
- Consumes: `POST /trips/start`, `PATCH /trips/{id}/checkpoint`, `PATCH /trips/{id}/finish` (Task 2).

- [ ] **Step 1: Rewrite trip-recorder.js**

Replace the full contents of `public/js/trip-recorder.js`:

```js
(function () {
  const $ = (id) => document.getElementById(id);
  const startBtn = $('start-btn'), stopBtn = $('stop-btn');
  const IDLE_MS = 5 * 60 * 1000;        // ponytail: idle auto-stop 5 menit, tuning di device asli
  const MAX_JUMP_KM = 1;                // ponytail: buang lonjakan >1km antar update (outlier GPS)
  const CHECKPOINT_MS = 10 * 1000;      // ponytail: auto-save tiap 10 detik, tuning keandalan vs jumlah request

  let watchId = null, last = null, distance = 0, startTs = 0, path = [], idleTimer = null, tick = null;
  let marker = null, liveLine = null;
  let tripId = null, checkpointTimer = null, lastSavedCount = 0;

  const token = () => document.querySelector('input[name="_token"]').value;

  const map = window.MuterinMap.init('ride-map');
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

  function progressBody() {
    return {
      distance_km: Number(distance.toFixed(2)),
      duration_seconds: Math.floor((Date.now() - startTs) / 1000),
      path,
    };
  }

  function sendCheckpoint() {
    if (!tripId || path.length === lastSavedCount) return; // nothing new since last save
    lastSavedCount = path.length;
    fetch(`/trips/${tripId}/checkpoint`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
      body: JSON.stringify(progressBody()),
    }).catch(() => { /* offline blip  retried next interval, data stays in memory */ });
  }

  async function start() {
    if (!navigator.geolocation) {
      $('gps-msg').textContent = 'Browser tidak mendukung GPS.';
      return;
    }
    navigator.geolocation.getCurrentPosition(async () => {
      // Create the draft server-side first so a crash mid-ride keeps whatever we checkpoint.
      let res;
      try {
        res = await fetch('/trips/start', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
          body: JSON.stringify({ motorcycle_id: $('motor-select').value }),
        }).then((r) => r.json());
      } catch {
        $('gps-msg').textContent = 'Gagal memulai perjalanan. Cek koneksi.';
        return;
      }
      tripId = res.trip_id;
      distance = 0; last = null; path = []; lastSavedCount = 0;
      startTs = Date.now();
      $('distance').textContent = '0.00';
      if (liveLine) { liveLine.remove(); liveLine = null; }
      if (marker) { marker.remove(); marker = null; }
      watchId = navigator.geolocation.watchPosition(onPos, onErr, { enableHighAccuracy: true, maximumAge: 0 });
      tick = setInterval(() => {
        $('duration').textContent = fmtDur(Math.floor((Date.now() - startTs) / 1000));
      }, 1000);
      checkpointTimer = setInterval(sendCheckpoint, CHECKPOINT_MS);
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
    clearInterval(checkpointTimer);
    clearTimeout(idleTimer);
    watchId = null;
    if (!tripId) { window.location.href = '/dashboard'; return; }
    await fetch(`/trips/${tripId}/finish`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
      body: JSON.stringify(progressBody()),
    });
    window.location.href = '/dashboard';
  }

  startBtn.addEventListener('click', start);
  stopBtn.addEventListener('click', stop);
})();
```

- [ ] **Step 2: Run the full test suite**

Run: `php artisan test`
Expected: all pass (frontend-only change; the backend endpoints it calls were tested in Task 2).

- [ ] **Step 3: Commit**

```bash
git add public/js/trip-recorder.js
git commit -m "feat: riding auto-saves trip every 10s (start/checkpoint/finish) so a crash can't lose it"
```

---

### Task 4: Recovery banner for unfinished trips

**Files:**
- Modify: `resources/views/riding/index.blade.php`
- Test: `tests/Feature/TripTest.php`

**Interfaces:**
- Consumes: `$unfinished` passed from `TripController::create()` (Task 2 already added it to the view data), `PATCH /trips/{id}/finish`, `DELETE /trips/{id}` (Task 2), `window.MuterinDialog` (Plan 1).

- [ ] **Step 1: Write the failing test**

In `tests/Feature/TripTest.php`, add:

```php
    public function test_riding_page_shows_recovery_banner_for_unfinished_trip(): void
    {
        $user = User::factory()->create();
        $motor = $this->motor($user);
        $motor->trips()->create(['distance_km' => 3.4, 'duration_seconds' => 200, 'status' => 'recording', 'started_at' => now()]);

        $this->actingAs($user)->get(route('riding'))
            ->assertOk()
            ->assertSee('Ada perjalanan yang belum selesai')
            ->assertSee('data-recover-trip', false);
    }

    public function test_riding_page_has_no_banner_without_unfinished_trip(): void
    {
        $user = User::factory()->create();
        $this->motor($user);

        $this->actingAs($user)->get(route('riding'))->assertDontSee('Ada perjalanan yang belum selesai');
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=TripTest`
Expected: FAIL  the banner markup doesn't exist in the view yet.

- [ ] **Step 3: Add the recovery banner to the riding view**

In `resources/views/riding/index.blade.php`, find:

```blade
    <div class="max-w-lg mx-auto p-4 sm:p-6 lg:p-8" id="riding-app">
        @if ($motorcycles->isEmpty())
```

Replace with:

```blade
    <div class="max-w-lg mx-auto p-4 sm:p-6 lg:p-8" id="riding-app">
        @isset($unfinished)
            @if ($unfinished)
                <div class="mb-4 bg-amber-50 border border-amber-200 rounded-2xl p-4"
                     data-recover-trip="{{ $unfinished->id }}">
                    <p class="text-sm font-semibold text-amber-800">Ada perjalanan yang belum selesai</p>
                    <p class="text-xs text-amber-700 mt-0.5">
                        {{ $unfinished->motorcycle->nickname ?? 'Motor' }}  {{ number_format($unfinished->distance_km, 2) }} km,
                        direkam {{ $unfinished->started_at?->diffForHumans() }}.
                    </p>
                    <div class="flex gap-2 mt-3">
                        <button data-recover-finish class="text-xs font-semibold px-3 py-2 rounded-lg bg-primary text-white hover:bg-primary-hover transition">Selesaikan</button>
                        <button data-recover-discard class="text-xs font-semibold px-3 py-2 rounded-lg border border-amber-300 text-amber-800 hover:bg-amber-100 transition">Buang</button>
                    </div>
                </div>
            @endif
        @endisset

        @if ($motorcycles->isEmpty())
```

- [ ] **Step 4: Add the recovery script**

In `resources/views/riding/index.blade.php`, find:

```blade
    @csrf
    @if ($motorcycles->isNotEmpty())
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/map-common.js') }}"></script>
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
    <script>
        (function () {
            const banner = document.querySelector('[data-recover-trip]');
            if (!banner) return;
            const id = banner.dataset.recoverTrip;
            const token = () => document.querySelector('input[name="_token"]').value;

            banner.querySelector('[data-recover-finish]').addEventListener('click', () => {
                fetch(`/trips/${id}/finish`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
                    body: JSON.stringify({ distance_km: {{ (float) ($unfinished->distance_km ?? 0) }}, duration_seconds: {{ (int) ($unfinished->duration_seconds ?? 0) }} }),
                }).then(() => location.reload());
            });

            banner.querySelector('[data-recover-discard]').addEventListener('click', async () => {
                const ok = await window.MuterinDialog.confirm('Buang perjalanan yang belum selesai ini?', { danger: true, confirmText: 'Buang' });
                if (!ok) return;
                fetch(`/trips/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
                }).then(() => location.reload());
            });
        })();
    </script>
</x-app-layout>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=TripTest`
Expected: PASS (9 tests  7 from Task 2 + 2 new banner tests)

- [ ] **Step 6: Commit**

```bash
git add resources/views/riding/index.blade.php tests/Feature/TripTest.php
git commit -m "feat: recovery banner to finish or discard an interrupted riding trip"
```

---

### Task 5: Final verification + demo data sanity

**Files:** None (verification only).

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: all tests pass. If any fail, fix before proceeding.

- [ ] **Step 2: Manual end-to-end browser verification (controller does this personally  needs real browser + GPS simulation)**

- `/riding`: press "Mulai Perjalanan"  confirm a draft trip row appears server-side (status `recording`) immediately, before pressing Stop (check via `php artisan tinker` or a second query). Simulate GPS movement (browser devtools geolocation) for >10s  confirm a checkpoint PATCH fires and the draft's `distance_km`/`path_json` grow while `current_odometer_km` stays unchanged. Press "Selesai"  confirm status flips to `completed`, `ended_at` set, and odometer increases exactly once.
- **Crash recovery:** start a ride, let one checkpoint save, then navigate away / reload WITHOUT pressing Stop. Return to `/riding`  confirm the amber "Ada perjalanan yang belum selesai" banner shows the checkpointed distance. Click "Selesaikan"  confirm the trip completes and odometer updates once. Repeat the crash, then click "Buang" (styled confirm)  confirm the draft is deleted and no odometer change.
- `/peta/rute`: confirm only completed trips are drawn (a lingering `recording` draft must not appear).
- Confirm the odometer never double-counts across any of the above (finish is idempotent).

- [ ] **Step 3: Report**

No commit for this task. If manual verification finds any issue, fix it in the relevant task's files, re-run the full suite, and commit the fix describing what was found.

---
