# Odometer Backbone & Dokumen Kendaraan (Amicta v4) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace GPS-trip-only odometer tracking with a reliable manual-first backbone (matching industry standard apps Drivvo/Fuelio/Otodiary), add vehicle document due-date reminders (STNK/plat/asuransi), and add an "other expenses" log — so every downstream feature (status, prediction, health score, Pusat Perhatian, Laporan) computes from trustworthy data instead of sparse/absent GPS trips.

**Architecture:** One new pure-calculation service (`OdometerService`) becomes the single write-path for odometer changes, consumed by every existing controller that currently mutates `current_odometer_km` directly. `MaintenancePredictionService::avgKmPerDay()` delegates to it instead of summing `Trip` records. Two small new features (vehicle documents, other expenses) follow the exact same Laravel + Blade + Alpine + Tailwind conventions already established in this codebase (`x-ui.*` components, `x-icon.*` SVGs, service classes matching `MaintenanceStatusService`'s style).

**Tech Stack:** Laravel 13, Blade/Alpine/Tailwind, PHPUnit (`php artisan test`), SQLite dev DB, Chart.js (already loaded via CDN on the Laporan page).

## Global Constraints

- Follow existing service pattern exactly: pure PHP classes in `app/Services/`, method-injected into controllers (see `FuelController::store(Request $request, FuelStatsService $stats)` for the established style) — do not introduce a DI container config or facades.
- Icons: SVG only, stroke-width 1.5, viewBox `0 0 24 24`, `{{ $attributes->merge([...]) }}` pattern — copy the structure of an existing icon file (e.g. `resources/views/components/icon/wallet.blade.php`) exactly.
- Colors: only existing Tailwind tokens (`primary`, `primary-soft`, `accent`, `status-green/yellow/red`, `muted`, `muted-fg`, `border`, `foreground`, `hero`). No new hex values, no gradients.
- Reuse existing Blade components: `x-ui.card`, `x-ui.button`, `x-ui.input`, `x-ui.progress`, `x-ui.badge`, `x-ui.hero`. Do not invent new base components.
- **Odometer never moves backward** — enforced once, in `OdometerService::record()`, via `ValidationException`. Every caller (controllers) lets this exception propagate to Laravel's default handler (redirect back with field error), matching how `$request->validate()` failures already behave everywhere else in this codebase.
- All new routes go inside the existing `Route::middleware('auth')->group(...)` block in `routes/web.php`.
- Every task ends with `php artisan test` passing (67 tests exist before this plan) plus new tests, before commit.
- Commit style: `feat: ...` / `fix: ...`, one commit per task step group as shown.
- Reference spec: `docs/superpowers/specs/2026-07-19-odometer-backbone-design.md` — read it if a task's rationale is unclear.

---

## Task 1: `OdometerReading` model, migration, `OdometerService`

**Files:**
- Create: `database/migrations/2026_07_19_100001_create_odometer_readings_table.php`
- Create: `app/Models/OdometerReading.php`
- Create: `app/Services/OdometerService.php`
- Modify: `app/Models/Motorcycle.php` (add `odometerReadings()` relation)
- Test: `tests/Unit/OdometerServiceTest.php`

**Interfaces:**
- Produces:
  - `OdometerReading` fields: `motorcycle_id, reading_km (int), recorded_at (date), source (enum: manual|fuel|trip|initial), note (string|null)`.
  - `Motorcycle::odometerReadings(): HasMany`.
  - `OdometerService::record(Motorcycle $motorcycle, int $km, \Illuminate\Support\Carbon $date, string $source, ?string $note = null): OdometerReading` — throws `\Illuminate\Validation\ValidationException` with key `odometer_km` if `$km < $motorcycle->current_odometer_km`.
  - `OdometerService::avgKmPerDay(Motorcycle $motorcycle): ?float`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/OdometerServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\OdometerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OdometerServiceTest extends TestCase
{
    use RefreshDatabase;

    private OdometerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new OdometerService();
    }

    public function test_record_creates_reading_and_raises_current_odometer(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $reading = $this->svc->record($motor, 1200, Carbon::parse('2026-07-19'), 'manual', 'test note');

        $this->assertEquals(1200, $reading->reading_km);
        $this->assertEquals('manual', $reading->source);
        $this->assertEquals(1200, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 1200, 'note' => 'test note']);
    }

    public function test_record_at_same_km_does_not_error_and_still_logs_reading(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->svc->record($motor, 1000, Carbon::parse('2026-07-19'), 'fuel');

        $this->assertEquals(1000, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 1000]);
    }

    public function test_record_rejects_odometer_lower_than_current(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 5000]);

        $this->expectException(ValidationException::class);

        try {
            $this->svc->record($motor, 4900, Carbon::parse('2026-07-19'), 'manual');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('odometer_km', $e->errors());
            $this->assertEquals(5000, $motor->fresh()->current_odometer_km);
            $this->assertDatabaseMissing('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 4900]);
            throw $e;
        }
    }

    public function test_avg_km_per_day_from_recent_readings_within_30_days(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $motor->odometerReadings()->create(['reading_km' => 940, 'recorded_at' => now()->subDays(10), 'source' => 'manual']);
        $motor->odometerReadings()->create(['reading_km' => 1000, 'recorded_at' => now()->subDays(5), 'source' => 'manual']);

        // delta 60 km, fixed /30 divisor -> 2.0 km/day
        $this->assertEquals(2.0, $this->svc->avgKmPerDay($motor));
    }

    public function test_avg_km_per_day_falls_back_to_lifetime_when_no_recent_readings(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 1000, 'current_odometer_km' => 1000,
        ]);

        $this->assertNull($this->svc->avgKmPerDay($motor));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd "D:\Ilyas Nur Rohman\Dicoding\Amicta" && php artisan test --filter=OdometerServiceTest`
Expected: FAIL — class `App\Services\OdometerService` not found.

- [ ] **Step 3: Create migration**

```bash
php artisan make:migration create_odometer_readings_table
```

Edit the generated file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odometer_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('reading_km');
            $table->date('recorded_at');
            $table->enum('source', ['manual', 'fuel', 'trip', 'initial']);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odometer_readings');
    }
};
```

- [ ] **Step 4: Create the model**

`app/Models/OdometerReading.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdometerReading extends Model
{
    protected $fillable = ['motorcycle_id', 'reading_km', 'recorded_at', 'source', 'note'];

    protected $casts = ['recorded_at' => 'date'];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }
}
```

- [ ] **Step 5: Add relation to Motorcycle**

In `app/Models/Motorcycle.php`, add alongside the existing `fuelLogs()` method:

```php
    public function odometerReadings(): HasMany
    {
        return $this->hasMany(OdometerReading::class);
    }
```

- [ ] **Step 6: Implement OdometerService**

`app/Services/OdometerService.php`:

```php
<?php

namespace App\Services;

use App\Models\Motorcycle;
use App\Models\OdometerReading;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OdometerService
{
    public function record(Motorcycle $motorcycle, int $km, Carbon $date, string $source, ?string $note = null): OdometerReading
    {
        if ($km < $motorcycle->current_odometer_km) {
            throw ValidationException::withMessages([
                'odometer_km' => "Odometer tidak boleh lebih kecil dari {$motorcycle->current_odometer_km} km (bacaan terakhir).",
            ]);
        }

        $reading = $motorcycle->odometerReadings()->create([
            'reading_km' => $km,
            'recorded_at' => $date,
            'source' => $source,
            'note' => $note,
        ]);

        if ($km > $motorcycle->current_odometer_km) {
            $motorcycle->update(['current_odometer_km' => $km]);
        }

        return $reading;
    }

    /**
     * ponytail: 30-day fixed window + lifetime fallback mirrors
     * MaintenancePredictionService's original trip-based logic — tuning
     * knob, not a fixed law.
     */
    public function avgKmPerDay(Motorcycle $motorcycle): ?float
    {
        $readings = $motorcycle->odometerReadings()
            ->where('recorded_at', '>=', now()->subDays(30))
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        if ($readings->count() >= 2) {
            $delta = $readings->last()->reading_km - $readings->first()->reading_km;
            if ($delta > 0) {
                return round($delta / 30, 2);
            }
        }

        $daysSinceCreated = max(1, $motorcycle->created_at->diffInDays(now()));
        $totalKm = $motorcycle->current_odometer_km - $motorcycle->initial_odometer_km;

        if ($totalKm <= 0) {
            return null;
        }

        return round($totalKm / $daysSinceCreated, 2);
    }
}
```

- [ ] **Step 7: Migrate and run tests**

Run: `php artisan migrate --no-interaction && php artisan test --filter=OdometerServiceTest`
Expected: PASS (5 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_19_100001_create_odometer_readings_table.php app/Models/OdometerReading.php app/Models/Motorcycle.php app/Services/OdometerService.php tests/Unit/OdometerServiceTest.php
git commit -m "feat: OdometerReading model and OdometerService — single write-path for odometer"
```

---

## Task 2: Wire odometer into motor creation + used-motorcycle onboarding

**Files:**
- Modify: `app/Http/Controllers/MotorcycleController.php`
- Modify: `resources/views/motorcycles/create.blade.php`
- Test: `tests/Feature/MotorcycleTest.php` (extend)

**Interfaces:**
- Consumes: `OdometerService::record()` (Task 1).
- Produces: `MotorcycleController::store()` records an `initial` reading; accepts optional `oli_last_km`, `ban_last_km`, `aki_last_km`, `servis_last_km` fields that seed `MaintenanceItem.last_service_odometer_km` for a used motorcycle.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/MotorcycleTest.php`, add to the existing test class:

```php
    public function test_creating_motorcycle_records_initial_odometer_reading(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('motorcycles.store'), [
            'nickname' => 'Beat', 'plat_nomor' => 'B 1 XYZ', 'initial_odometer_km' => 8000,
        ]);

        $motor = Motorcycle::where('nickname', 'Beat')->first();
        $this->assertDatabaseHas('odometer_readings', [
            'motorcycle_id' => $motor->id, 'reading_km' => 8000, 'source' => 'initial',
        ]);
    }

    public function test_creating_used_motorcycle_with_onboarding_checklist_sets_item_baselines(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('motorcycles.store'), [
            'nickname' => 'Beat Bekas', 'plat_nomor' => 'B 2 XYZ', 'initial_odometer_km' => 12000,
            'oli_last_km' => 10500, 'ban_last_km' => 3000,
        ]);

        $motor = Motorcycle::where('nickname', 'Beat Bekas')->first();
        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();
        $ban = $motor->maintenanceItems()->where('name', 'Ban')->first();
        $aki = $motor->maintenanceItems()->where('name', 'Aki')->first();

        $this->assertEquals(10500, $oli->last_service_odometer_km);
        $this->assertEquals(3000, $ban->last_service_odometer_km);
        // Untouched field falls back to the default booted() behavior (current odometer).
        $this->assertEquals(12000, $aki->last_service_odometer_km);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MotorcycleTest`
Expected: FAIL — `odometer_readings` table has no row for the new motor yet (store() doesn't call `OdometerService` yet).

- [ ] **Step 3: Update MotorcycleController**

In `app/Http/Controllers/MotorcycleController.php`, add the import `use App\Services\OdometerService;` and `use Illuminate\Support\Carbon;`, then replace the `store()` method and add two private helpers:

```php
    public function store(Request $request, OdometerService $odometer)
    {
        $data = $this->validated($request);
        $onboarding = $this->onboardingChecklist($request);

        $data['current_odometer_km'] = $data['initial_odometer_km'];
        $motorcycle = auth()->user()->motorcycles()->create($data);

        $odometer->record($motorcycle, $data['initial_odometer_km'], Carbon::today(), 'initial');
        $this->applyOnboardingChecklist($motorcycle, $onboarding);

        return redirect()->route('motorcycles.index')->with('status', 'Motor ditambahkan.');
    }
```

```php
    private function onboardingChecklist(Request $request): array
    {
        return $request->validate([
            'oli_last_km' => 'nullable|integer|min:0',
            'ban_last_km' => 'nullable|integer|min:0',
            'aki_last_km' => 'nullable|integer|min:0',
            'servis_last_km' => 'nullable|integer|min:0',
        ]);
    }

    private function applyOnboardingChecklist(Motorcycle $motorcycle, array $checklist): void
    {
        $map = [
            'oli_last_km' => 'Oli Mesin',
            'ban_last_km' => 'Ban',
            'aki_last_km' => 'Aki',
            'servis_last_km' => 'Servis Rutin',
        ];

        foreach ($map as $field => $itemName) {
            if (!empty($checklist[$field])) {
                $motorcycle->maintenanceItems()
                    ->where('name', $itemName)
                    ->update(['last_service_odometer_km' => $checklist[$field]]);
            }
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MotorcycleTest`
Expected: PASS (all MotorcycleTest tests, including the 2 new ones)

- [ ] **Step 5: Add onboarding section to the create form**

In `resources/views/motorcycles/create.blade.php`, inside the `<form>` right after the `initial_odometer_km` input and before the submit button, add:

```blade
                <div x-data="{ open: false }" class="border-t border-border pt-4">
                    <button type="button" @click="open = !open" class="text-sm text-primary font-medium hover:underline">
                        Riwayat Awal (opsional) — motor bekas?
                    </button>
                    <p x-show="!open" class="text-xs text-muted-fg mt-1">Kosongkan kalau motor baru / belum pernah diservis.</p>
                    <div x-show="open" x-cloak class="grid sm:grid-cols-2 gap-4 mt-3">
                        <x-ui.input name="oli_last_km" label="Oli terakhir diganti di km" type="number" placeholder="cth. 10500" />
                        <x-ui.input name="ban_last_km" label="Ban terakhir diganti di km" type="number" placeholder="cth. 3000" />
                        <x-ui.input name="aki_last_km" label="Aki terakhir diganti di km" type="number" placeholder="cth. 500" />
                        <x-ui.input name="servis_last_km" label="Servis rutin terakhir di km" type="number" placeholder="cth. 9000" />
                    </div>
                </div>
```

- [ ] **Step 6: Full test suite + manual verification**

Run: `php artisan test`
Expected: all pass (67 + 2 new = 69).

Start `php artisan serve`, log in, go to `/motorcycles/create`, add a motor with the "Riwayat Awal" section filled in, confirm it saves without error and the detail page shows the correct starting progress for the items you set.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/MotorcycleController.php resources/views/motorcycles/create.blade.php tests/Feature/MotorcycleTest.php
git commit -m "feat: record initial odometer reading and used-motorcycle onboarding checklist"
```

---

## Task 3: Wire odometer into fuel logging + fuel efficiency sanity warning

**Files:**
- Modify: `app/Http/Controllers/FuelController.php`
- Modify: `resources/views/bbm/index.blade.php`
- Test: `tests/Feature/FuelControllerTest.php` (modify one existing test, add one new)

**Interfaces:**
- Consumes: `OdometerService::record()` (Task 1), `FuelStatsService::latestKmPerLiter()` (existing).
- Produces: fuel odometer writes now go through `OdometerService` (rejecting backward odometer instead of silently ignoring it); flash `warning` session key when a fill's resulting km/liter exceeds 60.

- [ ] **Step 1: Update the existing "does not lower odometer" test**

In `tests/Feature/FuelControllerTest.php`, this test's premise no longer holds — a lower odometer is now rejected outright rather than silently ignored. Replace it:

```php
    public function test_storing_fuel_log_rejects_odometer_lower_than_current(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 5000]);

        $this->actingAs($user)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-19',
            'odometer_km' => 4900,
            'liters' => 3.0,
            'total_cost' => 45000,
        ])->assertSessionHasErrors('odometer_km');

        $this->assertEquals(5000, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseMissing('fuel_logs', ['motorcycle_id' => $motor->id, 'total_cost' => 45000]);
    }
```

Add a new test for the efficiency warning:

```php
    public function test_unrealistic_efficiency_flashes_a_warning(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $motor->fuelLogs()->create(['filled_at' => '2026-07-01', 'odometer_km' => 1000, 'liters' => 4, 'total_cost' => 60000, 'is_full_tank' => true]);

        // 500km on 2 liters = 250 km/l, well past the 60 km/l sanity threshold
        $response = $this->actingAs($user)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-10',
            'odometer_km' => 1500,
            'liters' => 2,
            'total_cost' => 30000,
            'is_full_tank' => '1',
        ]);

        $response->assertSessionHas('warning');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=FuelControllerTest`
Expected: FAIL — old odometer-lowering behavior still silently succeeds; no `warning` session key exists yet.

- [ ] **Step 3: Update FuelController**

Replace `app/Http/Controllers/FuelController.php`'s `store()` method (keep `index()` and `destroy()` unchanged) and add the `OdometerService` import:

```php
use App\Services\OdometerService;
use Illuminate\Support\Carbon;
```

```php
    public function store(Request $request, OdometerService $odometer, FuelStatsService $stats)
    {
        $data = $request->validate([
            'motorcycle_id' => 'required|exists:motorcycles,id',
            'filled_at' => 'required|date',
            'odometer_km' => 'required|integer|min:0',
            'liters' => 'required|numeric|min:0.1',
            'total_cost' => 'required|integer|min:0',
            'is_full_tank' => 'nullable|boolean',
            'note' => 'nullable|string|max:255',
        ]);

        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $data['is_full_tank'] = $request->boolean('is_full_tank', true);

        $odometer->record($motor, $data['odometer_km'], Carbon::parse($data['filled_at']), 'fuel');
        $motor->fuelLogs()->create($data);

        $warning = null;
        $latest = $stats->latestKmPerLiter($motor->fresh());
        if ($latest !== null && $latest > 60) {
            $warning = "Efisiensi {$latest} km/liter terlihat tidak biasa — cek kembali odometer atau jumlah liter yang diinput.";
        }

        return redirect()->route('bbm.index')
            ->with('status', 'Isi bensin dicatat.')
            ->with('warning', $warning);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=FuelControllerTest`
Expected: PASS (5 tests — 4 pre-existing minus the 1 rewritten, plus the 1 new)

- [ ] **Step 5: Display the warning flash in the BBM view**

In `resources/views/bbm/index.blade.php`, right after the existing `@if (session('status'))` block, add:

```blade
        @if (session('warning'))
            <div class="p-3 rounded-xl bg-amber-50 text-amber-700 text-sm font-medium">{{ session('warning') }}</div>
        @endif
```

- [ ] **Step 6: Full test suite + manual verification**

Run: `php artisan test`
Expected: all pass.

Manually verify: try submitting a fuel entry with an odometer lower than the motorcycle's current value — confirm you get a validation error, not a silent no-op.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/FuelController.php resources/views/bbm/index.blade.php tests/Feature/FuelControllerTest.php
git commit -m "fix: fuel logging rejects backward odometer via OdometerService, warns on unrealistic efficiency"
```

---

## Task 4: Wire odometer into trip recording

**Files:**
- Modify: `app/Http/Controllers/TripController.php`
- Test: `tests/Feature/TripTest.php` (extend)

**Interfaces:**
- Consumes: `OdometerService::record()` (Task 1).
- Produces: finishing a trip now also creates an `OdometerReading` with `source: trip`.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/TripTest.php`, add to the existing test class:

```php
    public function test_finishing_trip_records_an_odometer_reading(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 1000, 'current_odometer_km' => 1000,
        ]);

        $this->actingAs($user)->postJson(route('trips.store'), [
            'motorcycle_id' => $motor->id,
            'distance_km' => 12.5,
            'duration_seconds' => 1800,
        ]);

        $this->assertDatabaseHas('odometer_readings', [
            'motorcycle_id' => $motor->id, 'reading_km' => 1013, 'source' => 'trip',
        ]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TripTest`
Expected: FAIL — no `odometer_readings` row created yet.

- [ ] **Step 3: Update TripController**

In `app/Http/Controllers/TripController.php`, add `use App\Services\OdometerService;`, then replace the odometer-update line in `store()`:

Find:
```php
        $motor->increment('current_odometer_km', (int) round($data['distance_km']));
```

Replace with:
```php
        $newOdometer = $motor->current_odometer_km + (int) round($data['distance_km']);
        app(OdometerService::class)->record($motor, $newOdometer, now(), 'trip');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TripTest`
Expected: PASS (all TripTest tests including the new one)

- [ ] **Step 5: Full test suite + commit**

Run: `php artisan test`
Expected: all pass.

```bash
git add app/Http/Controllers/TripController.php tests/Feature/TripTest.php
git commit -m "feat: trip recording writes an odometer reading via OdometerService"
```

---

## Task 5: Manual "Update KM" feature

**Files:**
- Create: `app/Http/Controllers/OdometerReadingController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/dashboard.blade.php`
- Modify: `resources/views/motorcycles/show.blade.php`
- Test: `tests/Feature/OdometerReadingControllerTest.php`

**Interfaces:**
- Consumes: `OdometerService::record()` (Task 1).
- Produces: route `odometer.store` (POST `/odometer`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/OdometerReadingControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdometerReadingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_manually_update_odometer(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($user)->post(route('odometer.store'), [
            'motorcycle_id' => $motor->id,
            'reading_km' => 1250,
            'recorded_at' => '2026-07-19',
            'note' => 'cek rutin',
        ])->assertRedirect();

        $this->assertEquals(1250, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('odometer_readings', ['motorcycle_id' => $motor->id, 'reading_km' => 1250, 'source' => 'manual']);
    }

    public function test_cannot_update_odometer_of_other_users_motorcycle(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $owner->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($intruder)->post(route('odometer.store'), [
            'motorcycle_id' => $motor->id,
            'reading_km' => 1250,
            'recorded_at' => '2026-07-19',
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=OdometerReadingControllerTest`
Expected: FAIL — route `odometer.store` not defined.

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/OdometerReadingController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Services\OdometerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OdometerReadingController extends Controller
{
    public function store(Request $request, OdometerService $odometer)
    {
        $data = $request->validate([
            'motorcycle_id' => 'required|exists:motorcycles,id',
            'reading_km' => 'required|integer|min:0',
            'recorded_at' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $odometer->record($motor, $data['reading_km'], Carbon::parse($data['recorded_at']), 'manual', $data['note'] ?? null);

        return back()->with('status', 'Odometer diperbarui.');
    }
}
```

- [ ] **Step 4: Add route**

In `routes/web.php`, add `use App\Http\Controllers\OdometerReadingController;` to imports, and inside the auth group:

```php
    Route::post('odometer', [OdometerReadingController::class, 'store'])->name('odometer.store');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=OdometerReadingControllerTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Add "Update KM" action to motorcycle detail page**

In `resources/views/motorcycles/show.blade.php`, find the header row that shows the motorcycle's odometer/brand line (the `<p class="text-muted-fg flex items-center gap-1.5">` line with `<x-icon.gauge>`), and add a small toggle-form right after that paragraph, before the maintenance items loop:

```blade
        <div x-data="{ open: false }" class="bg-surface border border-border rounded-2xl p-4">
            <button type="button" @click="open = !open" class="text-sm font-semibold text-primary hover:underline">
                Update KM
            </button>
            <form x-show="open" x-cloak method="POST" action="{{ route('odometer.store') }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="motorcycle_id" value="{{ $motorcycle->id }}">
                <x-ui.input name="reading_km" label="Odometer sekarang (km)" type="number" required />
                <x-ui.input name="recorded_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <x-ui.button variant="primary" size="sm" type="submit">Simpan</x-ui.button>
            </form>
        </div>
```

- [ ] **Step 7: Add a lighter quick-action on the dashboard motor card**

In `resources/views/dashboard.blade.php`, inside the motor card header block (where the plat/odometer line is), add a small link next to it pointing to the motor's detail page where the Update KM form lives (keeps the dashboard uncluttered — the actual form lives on the detail page from Step 6):

Find the line:
```blade
                                    <p class="text-[11px] text-muted-fg tabular-nums">{{ $row['motor']->plat_nomor }} &middot; {{ number_format($row['motor']->current_odometer_km) }} km</p>
```

Add right after it (still inside the same `<div>`):
```blade
                                    <a href="{{ route('motorcycles.show', $row['motor']) }}#update-km" class="text-[11px] text-primary font-semibold hover:underline">Update KM</a>
```

And in `resources/views/motorcycles/show.blade.php`, add `id="update-km"` to the toggle button from Step 6 so the anchor link scrolls to it:

```blade
            <button type="button" id="update-km" @click="open = !open" class="text-sm font-semibold text-primary hover:underline">
```

- [ ] **Step 8: Full test suite + manual verification**

Run: `php artisan test`
Expected: all pass.

Start the dev server, log in, open a motorcycle's detail page, click "Update KM", submit a higher value, confirm the odometer and all progress bars update.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/OdometerReadingController.php routes/web.php resources/views/dashboard.blade.php resources/views/motorcycles/show.blade.php tests/Feature/OdometerReadingControllerTest.php
git commit -m "feat: manual Update KM action on dashboard and motorcycle detail"
```

---

## Task 6: Rewire prediction to read from OdometerService

**Files:**
- Modify: `app/Services/MaintenancePredictionService.php`
- Test: `tests/Unit/MaintenancePredictionServiceTest.php` (modify)

**Interfaces:**
- Consumes: `OdometerService::avgKmPerDay()` (Task 1).
- Produces: `MaintenancePredictionService::avgKmPerDay()` keeps its exact same signature and return type, but now delegates entirely to `OdometerService` instead of summing `Trip` records.

- [ ] **Step 1: Update the existing test that exercised trip-based data**

In `tests/Unit/MaintenancePredictionServiceTest.php`, this test's premise changes — `avgKmPerDay` now reads `OdometerReading` rows, not `Trip` rows. Replace `test_avg_km_per_day_from_recent_trips`:

```php
    public function test_avg_km_per_day_from_recent_odometer_readings(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $motor->odometerReadings()->create(['reading_km' => 940, 'recorded_at' => now()->subDays(10), 'source' => 'manual']);
        $motor->odometerReadings()->create(['reading_km' => 1000, 'recorded_at' => now()->subDays(5), 'source' => 'manual']);

        // delta 60 km over the fixed 30-day window -> 2.0 km/day
        $this->assertEquals(2.0, $this->svc->avgKmPerDay($motor));
    }
```

Leave `test_avg_km_per_day_falls_back_to_lifetime_when_no_recent_trips` and all `forItem()` tests unchanged — they don't depend on the trip-vs-reading distinction (the fallback test has no data at all either way; `forItem()` tests pass `avgKmPerDay` in directly as a parameter).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MaintenancePredictionServiceTest`
Expected: FAIL — `avgKmPerDay()` still reads `trips()`, so the new odometer-readings-only test returns the lifetime fallback (or null) instead of `2.0`.

- [ ] **Step 3: Update MaintenancePredictionService**

Replace the full contents of `app/Services/MaintenancePredictionService.php`:

```php
<?php

namespace App\Services;

use App\Models\MaintenanceItem;
use App\Models\Motorcycle;
use Illuminate\Support\Carbon;

class MaintenancePredictionService
{
    public function __construct(
        private MaintenanceStatusService $statusService = new MaintenanceStatusService(),
        private OdometerService $odometerService = new OdometerService(),
    ) {
    }

    public function avgKmPerDay(Motorcycle $motorcycle): ?float
    {
        return $this->odometerService->avgKmPerDay($motorcycle);
    }

    public function forItem(MaintenanceItem $item, int $currentOdometer, ?float $avgKmPerDay): array
    {
        if (!$avgKmPerDay || $avgKmPerDay <= 0) {
            return ['days_left' => null, 'predicted_date' => null];
        }

        $status = $this->statusService->forItem($item, $currentOdometer);
        $remainingKm = $status['remaining'];

        $daysLeft = $remainingKm > 0 ? (int) ceil($remainingKm / $avgKmPerDay) : 0;

        return [
            'days_left' => $daysLeft,
            'predicted_date' => Carbon::today()->addDays($daysLeft),
        ];
    }
}
```

(This removes the old inline 30-day-trip-sum + lifetime-fallback logic entirely — `OdometerService::avgKmPerDay()` from Task 1 is now the single implementation of that logic; `MaintenancePredictionService` just delegates.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MaintenancePredictionServiceTest`
Expected: PASS (all tests)

- [ ] **Step 5: Full test suite + commit**

Run: `php artisan test`
Expected: all pass — this also confirms `HealthScoreService`, `AttentionService`, and `DashboardController` (which all consume `MaintenancePredictionService`) still work correctly through the new delegation.

```bash
git add app/Services/MaintenancePredictionService.php tests/Unit/MaintenancePredictionServiceTest.php
git commit -m "refactor: MaintenancePredictionService delegates avgKmPerDay to OdometerService"
```

---

## Task 7: Dokumen Kendaraan (vehicle document due-date reminders)

**Files:**
- Create: `database/migrations/2026_07_19_100002_add_document_dates_to_motorcycles_table.php`
- Create: `app/Services/VehicleDocumentService.php`
- Create: `resources/views/components/icon/calendar.blade.php`
- Modify: `app/Models/Motorcycle.php`
- Modify: `app/Http/Controllers/MotorcycleController.php`
- Modify: `app/Services/AttentionService.php`
- Modify: `resources/views/motorcycles/create.blade.php`, `edit.blade.php`, `show.blade.php`
- Test: `tests/Unit/VehicleDocumentServiceTest.php`, `tests/Unit/AttentionServiceTest.php` (modify constructor calls)

**Interfaces:**
- Produces:
  - `motorcycles` gains `stnk_due_date`, `plat_due_date`, `insurance_due_date` (all nullable date).
  - `VehicleDocumentService::forMotorcycle(Motorcycle $m): array` → list of `['label' => string, 'due_date' => Carbon, 'days_left' => int, 'color' => 'green'|'yellow'|'red']`, only for fields that have a value.
  - `AttentionService` constructor gains a 4th param `VehicleDocumentService $documentService`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/VehicleDocumentServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\VehicleDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleDocumentService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new VehicleDocumentService();
    }

    public function test_only_filled_documents_are_returned(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'stnk_due_date' => now()->addDays(10),
        ]);

        $items = $this->svc->forMotorcycle($motor);

        $this->assertCount(1, $items);
        $this->assertEquals('Pajak STNK', $items[0]['label']);
    }

    public function test_color_bands(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'stnk_due_date' => now()->subDays(3),       // overdue -> red
            'plat_due_date' => now()->addDays(15),       // soon -> yellow
            'insurance_due_date' => now()->addDays(90),  // far -> green
        ]);

        $items = collect($this->svc->forMotorcycle($motor))->keyBy('label');

        $this->assertEquals('red', $items['Pajak STNK']['color']);
        $this->assertEquals('yellow', $items['Ganti Plat (STNK 5 Tahun)']['color']);
        $this->assertEquals('green', $items['Asuransi']['color']);
    }

    public function test_no_documents_returns_empty_array(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A']);

        $this->assertEmpty($this->svc->forMotorcycle($motor));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=VehicleDocumentServiceTest`
Expected: FAIL — class not found, and `motorcycles` table has no document columns yet.

- [ ] **Step 3: Create migration**

```bash
php artisan make:migration add_document_dates_to_motorcycles_table --table=motorcycles
```

Edit generated file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motorcycles', function (Blueprint $table) {
            $table->date('stnk_due_date')->nullable()->after('plat_nomor');
            $table->date('plat_due_date')->nullable()->after('stnk_due_date');
            $table->date('insurance_due_date')->nullable()->after('plat_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('motorcycles', function (Blueprint $table) {
            $table->dropColumn(['stnk_due_date', 'plat_due_date', 'insurance_due_date']);
        });
    }
};
```

- [ ] **Step 4: Update Motorcycle model**

In `app/Models/Motorcycle.php`, add the three new fields to `$fillable`:

```php
    protected $fillable = [
        'user_id', 'nickname', 'plat_nomor', 'brand', 'model', 'year',
        'initial_odometer_km', 'current_odometer_km', 'is_active',
        'stnk_due_date', 'plat_due_date', 'insurance_due_date',
    ];
```

Add to `$casts` (alongside the existing `is_active` cast):

```php
    protected $casts = [
        'is_active' => 'boolean',
        'stnk_due_date' => 'date',
        'plat_due_date' => 'date',
        'insurance_due_date' => 'date',
    ];
```

- [ ] **Step 5: Implement VehicleDocumentService**

`app/Services/VehicleDocumentService.php`:

```php
<?php

namespace App\Services;

use App\Models\Motorcycle;
use Illuminate\Support\Carbon;

class VehicleDocumentService
{
    private const DOCUMENTS = [
        'stnk_due_date' => 'Pajak STNK',
        'plat_due_date' => 'Ganti Plat (STNK 5 Tahun)',
        'insurance_due_date' => 'Asuransi',
    ];

    /**
     * ponytail: 30-day "soon" threshold mirrors AttentionService's
     * maintenance-item threshold — tuning knob, not a fixed law.
     */
    public function forMotorcycle(Motorcycle $motorcycle): array
    {
        $items = [];

        foreach (self::DOCUMENTS as $field => $label) {
            $dueDate = $motorcycle->{$field};
            if (!$dueDate) {
                continue;
            }

            $daysLeft = (int) Carbon::today()->diffInDays($dueDate, false);

            $items[] = [
                'label' => $label,
                'due_date' => $dueDate,
                'days_left' => $daysLeft,
                'color' => $daysLeft < 0 ? 'red' : ($daysLeft <= 30 ? 'yellow' : 'green'),
            ];
        }

        return $items;
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan migrate --no-interaction && php artisan test --filter=VehicleDocumentServiceTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Wire documents into AttentionService**

In `app/Services/AttentionService.php`, add the constructor parameter and a new loop. Update the constructor:

```php
    public function __construct(
        private MaintenanceStatusService $statusService,
        private MaintenancePredictionService $predictionService,
        private FuelStatsService $fuelStatsService,
        private VehicleDocumentService $documentService,
    ) {
    }
```

Inside `forUser()`, right after the existing per-motorcycle fuel-efficiency check (before the closing brace of the `foreach ($user->motorcycles as $motor)` loop), add:

```php
            foreach ($this->documentService->forMotorcycle($motor) as $doc) {
                if ($doc['color'] === 'red') {
                    $overdueText = $doc['days_left'] === 0 ? 'hari ini' : abs($doc['days_left']).' hari lalu';
                    $items[] = [
                        'severity' => 'red',
                        'text' => "Segera bayar {$doc['label']} — {$motor->nickname}, jatuh tempo {$overdueText}",
                        'url' => route('motorcycles.show', $motor),
                    ];
                } elseif ($doc['color'] === 'yellow') {
                    $items[] = [
                        'severity' => 'yellow',
                        'text' => "{$doc['label']} {$motor->nickname} jatuh tempo {$doc['days_left']} hari lagi",
                        'url' => route('motorcycles.show', $motor),
                    ];
                }
            }
```

- [ ] **Step 8: Update AttentionServiceTest constructor calls**

In `tests/Unit/AttentionServiceTest.php`, add `use App\Services\VehicleDocumentService;` and update `setUp()`:

```php
    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new AttentionService(
            new MaintenanceStatusService(),
            new MaintenancePredictionService(),
            new FuelStatsService(),
            new VehicleDocumentService(),
        );
    }
```

- [ ] **Step 9: Update MotorcycleController for document fields + show()**

In `app/Http/Controllers/MotorcycleController.php`, add `use App\Services\VehicleDocumentService;`, then:

Add the three document fields to the `validated()` method:

```php
            'stnk_due_date' => 'nullable|date',
            'plat_due_date' => 'nullable|date',
            'insurance_due_date' => 'nullable|date',
```

Update `show()` to also compute documents:

```php
    public function show(Motorcycle $motorcycle, MaintenanceStatusService $status, VehicleDocumentService $documents)
    {
        $this->authorizeOwner($motorcycle);
        $motorcycle->load('maintenanceItems.logs');

        $items = $motorcycle->maintenanceItems->map(fn ($item) => [
            'item' => $item,
            'status' => $status->forItem($item, $motorcycle->current_odometer_km),
        ]);

        $documentItems = $documents->forMotorcycle($motorcycle);

        return view('motorcycles.show', compact('motorcycle', 'items', 'documentItems'));
    }
```

- [ ] **Step 10: Calendar icon**

`resources/views/components/icon/calendar.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
    <line x1="16" y1="2" x2="16" y2="6"/>
    <line x1="8" y1="2" x2="8" y2="6"/>
    <line x1="3" y1="10" x2="21" y2="10"/>
</svg>
```

- [ ] **Step 11: Add document fields to create/edit forms**

In `resources/views/motorcycles/create.blade.php`, add a second optional collapsible section right after the "Riwayat Awal" one added in Task 2:

```blade
                <div x-data="{ open: false }" class="border-t border-border pt-4">
                    <button type="button" @click="open = !open" class="text-sm text-primary font-medium hover:underline">
                        Dokumen Kendaraan (opsional)
                    </button>
                    <div x-show="open" x-cloak class="grid sm:grid-cols-3 gap-4 mt-3">
                        <x-ui.input name="stnk_due_date" label="Jatuh Tempo Pajak STNK" type="date" />
                        <x-ui.input name="plat_due_date" label="Jatuh Tempo Ganti Plat (5th)" type="date" />
                        <x-ui.input name="insurance_due_date" label="Jatuh Tempo Asuransi" type="date" />
                    </div>
                </div>
```

In `resources/views/motorcycles/edit.blade.php`, add the same section but with existing values, right before the submit button:

```blade
                <div x-data="{ open: true }" class="border-t border-border pt-4">
                    <button type="button" @click="open = !open" class="text-sm text-primary font-medium hover:underline">
                        Dokumen Kendaraan (opsional)
                    </button>
                    <div x-show="open" x-cloak class="grid sm:grid-cols-3 gap-4 mt-3">
                        <x-ui.input name="stnk_due_date" label="Jatuh Tempo Pajak STNK" type="date" :value="old('stnk_due_date', $motorcycle->stnk_due_date?->toDateString())" />
                        <x-ui.input name="plat_due_date" label="Jatuh Tempo Ganti Plat (5th)" type="date" :value="old('plat_due_date', $motorcycle->plat_due_date?->toDateString())" />
                        <x-ui.input name="insurance_due_date" label="Jatuh Tempo Asuransi" type="date" :value="old('insurance_due_date', $motorcycle->insurance_due_date?->toDateString())" />
                    </div>
                </div>
```

- [ ] **Step 12: Add Dokumen card to motor detail page**

In `resources/views/motorcycles/show.blade.php`, add this card right after the "Update KM" block added in Task 5, before the maintenance items loop (only rendered when there's at least one document to show):

```blade
        @if (count($documentItems))
            <div class="bg-surface border border-border rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-border bg-muted/40 flex items-center gap-2">
                    <x-icon.calendar class="w-4 h-4 text-primary"/>
                    <h3 class="font-heading font-bold text-foreground text-sm">Dokumen</h3>
                </div>
                <div class="p-3 space-y-1">
                    @foreach ($documentItems as $doc)
                        <div class="flex items-center justify-between p-3 rounded-xl">
                            <span class="text-sm text-foreground">{{ $doc['label'] }}</span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg
                                {{ $doc['color'] === 'red' ? 'bg-red-50 text-red-600' : ($doc['color'] === 'yellow' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">
                                @if ($doc['days_left'] < 0)
                                    Lewat {{ abs($doc['days_left']) }} hari
                                @elseif ($doc['days_left'] === 0)
                                    Jatuh tempo hari ini
                                @else
                                    {{ $doc['days_left'] }} hari lagi
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
```

- [ ] **Step 13: Full test suite + manual verification**

Run: `php artisan test`
Expected: all pass.

Manually verify: edit a motorcycle, set the STNK due date to a date in the past, confirm it shows red on the detail page AND appears in the dashboard's Pusat Perhatian.

- [ ] **Step 14: Commit**

```bash
git add database/migrations/2026_07_19_100002_add_document_dates_to_motorcycles_table.php app/Services/VehicleDocumentService.php app/Models/Motorcycle.php app/Http/Controllers/MotorcycleController.php app/Services/AttentionService.php resources/views/components/icon/calendar.blade.php resources/views/motorcycles/create.blade.php resources/views/motorcycles/edit.blade.php resources/views/motorcycles/show.blade.php tests/Unit/VehicleDocumentServiceTest.php tests/Unit/AttentionServiceTest.php
git commit -m "feat: vehicle document due-date reminders (STNK/plat/asuransi) integrated into Pusat Perhatian"
```

---

## Task 8: Pengeluaran Lain (other expenses)

**Files:**
- Create: `database/migrations/2026_07_19_100003_create_other_expenses_table.php`
- Create: `app/Models/OtherExpense.php`
- Create: `app/Http/Controllers/OtherExpenseController.php`
- Modify: `app/Models/Motorcycle.php`
- Modify: `app/Http/Controllers/HistoryController.php`
- Modify: `app/Http/Controllers/ReportController.php`
- Modify: `resources/views/history/index.blade.php`
- Modify: `resources/views/laporan/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OtherExpenseControllerTest.php`

**Interfaces:**
- Produces:
  - `OtherExpense` fields: `motorcycle_id, category (enum: asuransi|parkir|cuci_motor|aksesoris|lain_lain), amount (int), expense_date (date), note (string|null)`.
  - `Motorcycle::otherExpenses(): HasMany`.
  - Routes: `other-expenses.store` (POST), `other-expenses.destroy` (DELETE).
  - `HistoryController`'s `$totalCost` now includes other-expense amounts; `$breakdown` includes other-expense categories.
  - `ReportController`'s `$tco` now includes other-expense total; `$trend` gains a third `other` key per month.

- [ ] **Step 1: Write the failing test**

`tests/Feature/OtherExpenseControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtherExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_other_expense(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($user)->post(route('other-expenses.store'), [
            'motorcycle_id' => $motor->id,
            'category' => 'asuransi',
            'amount' => 500000,
            'expense_date' => '2026-07-19',
            'note' => 'Premi tahunan',
        ])->assertRedirect();

        $this->assertDatabaseHas('other_expenses', [
            'motorcycle_id' => $motor->id, 'category' => 'asuransi', 'amount' => 500000,
        ]);
    }

    public function test_cannot_record_expense_for_other_users_motorcycle(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $owner->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($intruder)->post(route('other-expenses.store'), [
            'motorcycle_id' => $motor->id, 'category' => 'parkir', 'amount' => 5000, 'expense_date' => '2026-07-19',
        ])->assertForbidden();
    }

    public function test_can_delete_own_expense(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $expense = $motor->otherExpenses()->create([
            'category' => 'parkir', 'amount' => 5000, 'expense_date' => '2026-07-19',
        ]);

        $this->actingAs($user)->delete(route('other-expenses.destroy', $expense))->assertRedirect();
        $this->assertDatabaseMissing('other_expenses', ['id' => $expense->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=OtherExpenseControllerTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Create migration**

```bash
php artisan make:migration create_other_expenses_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['asuransi', 'parkir', 'cuci_motor', 'aksesoris', 'lain_lain']);
            $table->unsignedInteger('amount');
            $table->date('expense_date');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_expenses');
    }
};
```

- [ ] **Step 4: Create model + Motorcycle relation**

`app/Models/OtherExpense.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtherExpense extends Model
{
    protected $fillable = ['motorcycle_id', 'category', 'amount', 'expense_date', 'note'];

    protected $casts = ['expense_date' => 'date'];

    public const CATEGORY_LABELS = [
        'asuransi' => 'Asuransi',
        'parkir' => 'Parkir',
        'cuci_motor' => 'Cuci Motor',
        'aksesoris' => 'Aksesoris',
        'lain_lain' => 'Lain-lain',
    ];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }
}
```

In `app/Models/Motorcycle.php`, add alongside `odometerReadings()`:

```php
    public function otherExpenses(): HasMany
    {
        return $this->hasMany(OtherExpense::class);
    }
```

- [ ] **Step 5: Create controller**

`app/Http/Controllers/OtherExpenseController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Models\OtherExpense;
use Illuminate\Http\Request;

class OtherExpenseController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'motorcycle_id' => 'required|exists:motorcycles,id',
            'category' => 'required|in:asuransi,parkir,cuci_motor,aksesoris,lain_lain',
            'amount' => 'required|integer|min:0',
            'expense_date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $motor->otherExpenses()->create($data);

        return back()->with('status', 'Pengeluaran dicatat.');
    }

    public function destroy(OtherExpense $otherExpense)
    {
        abort_unless($otherExpense->motorcycle->user_id === auth()->id(), 403);
        $otherExpense->delete();

        return back()->with('status', 'Pengeluaran dihapus.');
    }
}
```

- [ ] **Step 6: Add routes**

In `routes/web.php`, add `use App\Http\Controllers\OtherExpenseController;` and inside the auth group:

```php
    Route::post('other-expenses', [OtherExpenseController::class, 'store'])->name('other-expenses.store');
    Route::delete('other-expenses/{otherExpense}', [OtherExpenseController::class, 'destroy'])->name('other-expenses.destroy');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan migrate --no-interaction && php artisan test --filter=OtherExpenseControllerTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Extend HistoryController (Biaya & Servis page)**

Read `app/Http/Controllers/HistoryController.php` first, then update `__invoke()` and add a private helper. Replace the method:

```php
    public function __invoke()
    {
        $logs = $this->serviceLogs();
        $otherExpenses = $this->otherExpenses();

        $serviceCost = $logs->sum('cost');
        $otherCost = $otherExpenses->sum('amount');
        $totalCost = $serviceCost + $otherCost;

        $servicedCount = $logs->count();
        $avgCost = $servicedCount ? (int) round($serviceCost / $servicedCount) : 0;
        $thisMonthCost = $logs->where('serviced_at', '>=', now()->startOfMonth())->sum('cost')
            + $otherExpenses->where('expense_date', '>=', now()->startOfMonth())->sum('amount');

        $breakdown = $logs->groupBy(fn ($log) => $log->item->name)
            ->map(fn ($group) => $group->sum('cost'))
            ->merge(
                $otherExpenses->groupBy(fn ($e) => \App\Models\OtherExpense::CATEGORY_LABELS[$e->category])
                    ->map(fn ($group) => $group->sum('amount'))
            )
            ->sortDesc();

        return view('history.index', compact('logs', 'otherExpenses', 'totalCost', 'servicedCount', 'avgCost', 'thisMonthCost', 'breakdown'));
    }
```

Add the private helper (alongside the existing `serviceLogs()` method):

```php
    private function otherExpenses()
    {
        $userId = auth()->id();

        return \App\Models\OtherExpense::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))
            ->with('motorcycle')->latest('expense_date')->get();
    }
```

- [ ] **Step 9: Extend the Biaya & Servis view**

Read `resources/views/history/index.blade.php` first. Add an "+ Catat Pengeluaran Lain" button next to the existing "Unduh PDF" button inside the `<x-slot:side>` block:

```blade
                <x-ui.button variant="white" type="button" x-data @click="$dispatch('open-expense-form')">Catat Pengeluaran Lain</x-ui.button>
```

Add the toggle form + other-expenses list as a new card, right after the existing "Riwayat Pengeluaran" (service log table) card, still inside the same outer wrapper:

```blade
        <div x-data="{ open: false }" @open-expense-form.window="open = true" x-cloak class="bg-surface border border-border rounded-2xl overflow-hidden">
            <button @click="open = !open" type="button" class="w-full p-5 flex items-center justify-between text-left border-b border-border bg-muted/40">
                <h3 class="font-heading font-bold text-foreground text-sm">Pengeluaran Lain</h3>
                <span class="text-primary text-sm font-semibold" x-text="open ? 'Tutup' : 'Tambah'"></span>
            </button>
            <form x-show="open" method="POST" action="{{ route('other-expenses.store') }}" class="p-5 grid sm:grid-cols-2 gap-4 border-b border-border">
                @csrf
                <label class="space-y-1.5">
                    <span class="block text-sm font-medium text-foreground">Motor</span>
                    <select name="motorcycle_id" required class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach (auth()->user()->motorcycles as $m)
                            <option value="{{ $m->id }}" @selected($m->is_active)>{{ $m->nickname }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1.5">
                    <span class="block text-sm font-medium text-foreground">Kategori</span>
                    <select name="category" required class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach (\App\Models\OtherExpense::CATEGORY_LABELS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ui.input name="amount" label="Jumlah (Rp)" type="number" required />
                <x-ui.input name="expense_date" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <div class="sm:col-span-2">
                    <x-ui.button variant="primary" type="submit">Simpan</x-ui.button>
                </div>
            </form>
            <div class="p-3 space-y-1 max-h-72 overflow-y-auto">
                @forelse ($otherExpenses as $expense)
                    <div class="flex items-center justify-between p-3 rounded-xl hover:bg-muted/40">
                        <div>
                            <p class="text-sm font-medium text-foreground">{{ \App\Models\OtherExpense::CATEGORY_LABELS[$expense->category] }} — {{ $expense->motorcycle->nickname }}</p>
                            <p class="text-[11px] text-muted-fg">{{ $expense->expense_date->format('d M Y') }}</p>
                        </div>
                        <span class="text-sm font-bold text-foreground tabular-nums">Rp{{ number_format($expense->amount) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted-fg p-3">Belum ada pengeluaran lain.</p>
                @endforelse
            </div>
        </div>
```

- [ ] **Step 10: Extend ReportController**

Read `app/Http/Controllers/ReportController.php` first, then update `__invoke()`:

```php
    public function __invoke(FuelStatsService $fuelStats)
    {
        $userId = auth()->id();

        $fuelLogs = \App\Models\FuelLog::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))->get();
        $serviceLogs = \App\Models\MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', $userId))->get();
        $otherExpenses = \App\Models\OtherExpense::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))->get();

        $totalFuelCost = (int) $fuelLogs->sum('total_cost');
        $totalServiceCost = (int) $serviceLogs->sum('cost');
        $totalOtherCost = (int) $otherExpenses->sum('amount');
        $tco = $totalFuelCost + $totalServiceCost + $totalOtherCost;

        $motorcycles = auth()->user()->motorcycles;
        $totalKm = (int) $motorcycles->sum(fn ($m) => max(0, $m->current_odometer_km - $m->initial_odometer_km));
        $costPerKm = $totalKm > 0 ? (int) round($tco / $totalKm) : null;

        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $monthlyFuel = $fuelLogs->groupBy(fn ($l) => $l->filled_at->format('Y-m'));
        $monthlyService = $serviceLogs->groupBy(fn ($l) => $l->serviced_at->format('Y-m'));
        $monthlyOther = $otherExpenses->groupBy(fn ($e) => $e->expense_date->format('Y-m'));

        $trend = $months->map(fn ($m) => [
            'month' => $m,
            'fuel' => (int) $monthlyFuel->get($m, collect())->sum('total_cost'),
            'service' => (int) $monthlyService->get($m, collect())->sum('cost'),
            'other' => (int) $monthlyOther->get($m, collect())->sum('amount'),
        ])->values();

        $efficiencySeries = $motorcycles->mapWithKeys(fn ($m) => [$m->nickname => $fuelStats->consumptionSeries($m)]);
        $efficiencyLabels = $efficiencySeries->flatten(1)->pluck('date')->sort()->unique()->values();

        return view('laporan.index', compact(
            'totalFuelCost', 'totalServiceCost', 'totalOtherCost', 'tco', 'costPerKm', 'trend', 'efficiencySeries', 'efficiencyLabels'
        ));
    }
```

- [ ] **Step 11: Extend Laporan view**

Read `resources/views/laporan/index.blade.php` first. Add a 5th stat card for "Pengeluaran Lain" next to the existing 4 (Total, Biaya/KM, BBM, Servis) — adjust the grid to `sm:grid-cols-2 lg:grid-cols-5`:

```blade
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                    <x-icon.wallet class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Pengeluaran Lain</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $totalOtherCost }}">0</span></p>
            </div>
```

In the trend chart's `<script>` block, add a third dataset (find the `datasets: [...]` array for `trend-chart` and add a third entry):

```js
                        { label: 'Lainnya', data: {!! json_encode($trend->pluck('other')) !!}, backgroundColor: '#64748B' },
```

- [ ] **Step 12: Full test suite + manual verification**

Run: `php artisan test`
Expected: all pass.

Manually verify: on `/history`, add a "Parkir" expense, confirm it shows in the new Pengeluaran Lain list and the donut chart's total updates; on `/laporan`, confirm the 5th stat card and the trend chart's 3rd bar series show non-zero values.

- [ ] **Step 13: Commit**

```bash
git add database/migrations/2026_07_19_100003_create_other_expenses_table.php app/Models/OtherExpense.php app/Http/Controllers/OtherExpenseController.php app/Models/Motorcycle.php app/Http/Controllers/HistoryController.php app/Http/Controllers/ReportController.php resources/views/history/index.blade.php resources/views/laporan/index.blade.php routes/web.php tests/Feature/OtherExpenseControllerTest.php
git commit -m "feat: Pengeluaran Lain (other expenses) integrated into Biaya & Servis and Laporan"
```

---

## Task 9: Final verification and demo data refresh

**Files:** `database/seeders/DemoDataSeeder.php` (modify) — verification only otherwise.

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: all tests pass. If any fail, fix before proceeding.

- [ ] **Step 2: Rebuild frontend assets**

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 3: Extend the demo seeder**

Read `database/seeders/DemoDataSeeder.php` first (it seeds `demo@amicta.test` with two motorcycles, "Beat Ilyas" and "NMAX Kantor"). Add odometer readings, document due dates, and other-expense entries.

In `seedMotor1()` (Beat Ilyas), after the existing `fuelLogs()->createMany([...])` call, add:

```php
        $motor->update([
            'stnk_due_date' => now()->subDays(3),   // overdue -> demonstrates red Pusat Perhatian item
            'plat_due_date' => now()->addYears(2),
        ]);

        $motor->odometerReadings()->createMany([
            ['reading_km' => 500, 'recorded_at' => '2026-01-01', 'source' => 'initial'],
            ['reading_km' => 9800, 'recorded_at' => '2026-07-05', 'source' => 'fuel'],
            ['reading_km' => 11200, 'recorded_at' => now()->subDays(10), 'source' => 'manual'],
            ['reading_km' => 12450, 'recorded_at' => now()->subDays(2), 'source' => 'manual'],
        ]);

        $motor->otherExpenses()->createMany([
            ['category' => 'asuransi', 'amount' => 450000, 'expense_date' => '2026-02-01', 'note' => 'Premi tahunan'],
            ['category' => 'parkir', 'amount' => 15000, 'expense_date' => now()->subDays(4)->toDateString()],
        ]);
```

In `seedMotor2()` (NMAX Kantor), after its `fuelLogs()->createMany([...])` call, add:

```php
        $motor->update([
            'stnk_due_date' => now()->addDays(20),  // due soon -> demonstrates yellow Pusat Perhatian item
        ]);

        $motor->odometerReadings()->createMany([
            ['reading_km' => 0, 'recorded_at' => '2026-01-01', 'source' => 'initial'],
            ['reading_km' => 5200, 'recorded_at' => '2026-06-15', 'source' => 'fuel'],
            ['reading_km' => 6200, 'recorded_at' => now()->subDays(6), 'source' => 'manual'],
        ]);

        $motor->otherExpenses()->createMany([
            ['category' => 'cuci_motor', 'amount' => 25000, 'expense_date' => now()->subDays(1)->toDateString()],
        ]);
```

- [ ] **Step 4: Re-run the seeder**

Run: `php artisan db:seed --class=DemoDataSeeder --no-interaction`
Expected: completes without error.

- [ ] **Step 5: Verify via tinker**

Run:
```bash
php artisan tinker --execute="
\$u = App\Models\User::where('email','demo@amicta.test')->first();
foreach (\$u->motorcycles as \$m) {
    echo \$m->nickname . ': ' . \$m->odometerReadings()->count() . ' readings, ' . \$m->otherExpenses()->count() . ' other expenses, STNK due ' . (\$m->stnk_due_date?->toDateString() ?? 'null') . PHP_EOL;
}
\$prediction = new App\Services\MaintenancePredictionService();
foreach (\$u->motorcycles as \$m) {
    echo \$m->nickname . ' avgKmPerDay: ' . (\$prediction->avgKmPerDay(\$m) ?? 'null') . PHP_EOL;
}
"
```
Expected: both motorcycles show non-zero reading/expense counts, correct due dates, and a non-null `avgKmPerDay`.

- [ ] **Step 6: Manual end-to-end browser verification**

Start `php artisan serve`, log in as `demo@amicta.test` / `password123`, and check:
- `/dashboard` — Pusat Perhatian shows a red "Segera bayar Pajak STNK — Beat Ilyas" item and a yellow "Pajak STNK NMAX Kantor jatuh tempo ~20 hari lagi" item.
- A motorcycle detail page — "Update KM" button works; "Dokumen" card shows the right colors.
- `/history` — "Pengeluaran Lain" section lists the seeded asuransi/parkir/cuci_motor entries; total cost includes them.
- `/laporan` — 5-card stat row includes "Pengeluaran Lain"; trend chart shows a 3rd bar series.
- Add a new motorcycle via `/motorcycles/create` with the "Riwayat Awal" and "Dokumen Kendaraan" sections filled in — confirm both apply correctly on the resulting detail page.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/DemoDataSeeder.php
git commit -m "feat: seed odometer readings, vehicle documents, and other expenses for demo data"
```

---

## Self-Review

**Spec coverage:** All 9 items from the spec's §2 summary table map to tasks — items 1-7 (odometer backbone) → Tasks 1-6; item 8 (Dokumen Kendaraan) → Task 7; item 9 (Pengeluaran Lain) → Task 8. Task 9 is the final verification task called for by the spec's demo-data-consistency concern (§10 risk table, "seeder demo diupdate agar tetap konsisten").

**Placeholder scan:** No TBD/TODO; every step has complete, runnable code.

**Type consistency:** `OdometerService::record()`'s signature (`Motorcycle, int, Carbon, string, ?string`) is used identically in every calling controller (Motorcycle/Fuel/Trip/OdometerReading). `OdometerService::avgKmPerDay()` returns `?float`, matching `MaintenancePredictionService::avgKmPerDay()`'s pre-existing return type exactly (pure delegation, no signature drift). `VehicleDocumentService::forMotorcycle()`'s return shape (`label/due_date/days_left/color`) is read consistently by both `MotorcycleController::show()` (view) and `AttentionService::forUser()`. `AttentionService`'s constructor signature change (4th param) is applied consistently to its only manual instantiation site (`AttentionServiceTest`) — `DashboardController` uses method injection, which Laravel's container resolves automatically without needing a code change. `OtherExpense::CATEGORY_LABELS` is referenced identically in `HistoryController`, `history/index.blade.php`, and `ReportController`.

**Deliberate behavior changes flagged for reviewers:** Task 3 changes `FuelController`'s backward-odometer handling from silent-ignore to hard rejection — this is a disclosed, spec-mandated change (§4/§7), not a regression; the existing test for the old behavior is explicitly rewritten in the same task rather than left stale.
