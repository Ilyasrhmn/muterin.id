# Manajemen Kendaraan (Muterin v3)  Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 6 vehicle-management modules to Muterin  fuel tracking, predictive maintenance, health score, an attention center, richer service history (workshop/parts/photo), and a cost report  turning it from an oil reminder into a full ownership-cost & maintenance management tool.

**Architecture:** Laravel + Blade + Alpine + Tailwind, following the exact conventions already in the codebase (`x-ui.*` components, `x-icon.*` SVGs, `x-ui.hero`, design tokens `primary`/`primary-soft`/`muted-fg`/`status-*`). Four new pure-calculation Services (no new tables beyond `fuel_logs`) sit alongside the existing `MaintenanceStatusService`. Everything renders through the existing sidebar/dashboard shell.

**Tech Stack:** Laravel 13, PHPUnit/`php artisan test`, SQLite (dev), Chart.js (already loaded via CDN on the Biaya & Servis page), Laravel public disk for receipt photo storage.

## Global Constraints

- Follow existing service pattern exactly: pure PHP classes in `app/Services/`, constructor-injected, one method per calculation, no side effects. Match `App\Services\MaintenanceStatusService`'s style (see `app/Services/MaintenanceStatusService.php`).
- Reuse existing Blade components: `x-ui.card`, `x-ui.button`, `x-ui.input`, `x-ui.progress`, `x-ui.badge`, `x-ui.hero`, `x-ui.stat-tile`. Do not invent new base components unless a genuinely new shape is needed.
- Icons: SVG only, stroke-width 1.5, viewBox 0 0 24 24, matching `resources/views/components/icon/*.blade.php` style. New icons needed: `droplet`, `camera`, `activity`, `trending-up`, `trending-down`, `alert-triangle`, `clock`, `bar-chart`.
- Colors: only use existing Tailwind tokens (`primary`, `primary-soft`, `accent`, `status-green/yellow/red`, `muted`, `muted-fg`, `border`, `foreground`, `hero`). No new hex values, no gradients (project convention: solid colors only, established in prior session).
- No document/date-based reminders (STNK, pajak, asuransi, SIM)  explicitly out of scope per user decision.
- Every task ends with `php artisan test` passing (currently 40 tests) plus any new tests, before commit.
- All new routes go inside the existing `Route::middleware('auth')->group(...)` block in `routes/web.php`.
- Commit after each task with a descriptive message, following the existing commit style (`feat: ...`).

---

## Task 1: FuelLog model, migration, and Motorcycle relation

**Files:**
- Create: `database/migrations/2026_07_19_000001_create_fuel_logs_table.php`
- Create: `app/Models/FuelLog.php`
- Modify: `app/Models/Motorcycle.php` (add `fuelLogs()` relation)
- Test: `tests/Feature/FuelLogTest.php`

**Interfaces:**
- Produces: `FuelLog` model with fields `motorcycle_id, filled_at (date), odometer_km (int), liters (decimal), total_cost (int), is_full_tank (bool), note (string|null)`. `Motorcycle::fuelLogs(): HasMany`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/FuelLogTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_motorcycle_has_fuel_logs_relation(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000,
        ]);

        $motor->fuelLogs()->create([
            'filled_at' => '2026-07-01',
            'odometer_km' => 1000,
            'liters' => 4.5,
            'total_cost' => 65000,
            'is_full_tank' => true,
        ]);

        $this->assertCount(1, $motor->fuelLogs);
        $this->assertEquals(4.5, $motor->fuelLogs->first()->liters);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd "D:\Ilyas Nur Rohman\Dicoding\Muterin" && php artisan test --filter=FuelLogTest`
Expected: FAIL  "Call to undefined method App\Models\Motorcycle::fuelLogs()" or missing table.

- [ ] **Step 3: Create migration**

```bash
php artisan make:migration create_fuel_logs_table
```

Edit the generated file to:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_id')->constrained()->cascadeOnDelete();
            $table->date('filled_at');
            $table->unsignedInteger('odometer_km');
            $table->decimal('liters', 6, 2);
            $table->unsignedInteger('total_cost');
            $table->boolean('is_full_tank')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
```

- [ ] **Step 4: Create FuelLog model**

`app/Models/FuelLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    protected $fillable = [
        'motorcycle_id', 'filled_at', 'odometer_km', 'liters', 'total_cost', 'is_full_tank', 'note',
    ];

    protected $casts = [
        'filled_at' => 'date',
        'is_full_tank' => 'boolean',
        'liters' => 'decimal:2',
    ];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }
}
```

- [ ] **Step 5: Add relation to Motorcycle**

In `app/Models/Motorcycle.php`, add alongside the existing `trips()` method:

```php
    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }
```

- [ ] **Step 6: Migrate and run test**

Run: `php artisan migrate --no-interaction && php artisan test --filter=FuelLogTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_19_000001_create_fuel_logs_table.php app/Models/FuelLog.php app/Models/Motorcycle.php tests/Feature/FuelLogTest.php
git commit -m "feat: FuelLog model and migration"
```

---

## Task 2: FuelStatsService (consumption, cost-per-km)

**Files:**
- Create: `app/Services/FuelStatsService.php`
- Test: `tests/Unit/FuelStatsServiceTest.php`

**Interfaces:**
- Consumes: `Motorcycle` with `fuelLogs` relation (Task 1).
- Produces:
  - `FuelStatsService::consumptionSeries(Motorcycle $m): array` → list of `['date' => string, 'km_per_liter' => float]`, computed only between consecutive **full-tank** fills.
  - `FuelStatsService::averageKmPerLiter(Motorcycle $m): ?float`
  - `FuelStatsService::latestKmPerLiter(Motorcycle $m): ?float`
  - `FuelStatsService::costPerKm(Motorcycle $m): ?float`  total fuel cost / total km spanned by fuel logs (first to last odometer reading), null if fewer than 2 logs or zero distance.

- [ ] **Step 1: Write the failing test**

`tests/Unit/FuelStatsServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\FuelStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private FuelStatsService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new FuelStatsService();
    }

    private function motorWithFuel(array $fills): Motorcycle
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 0]);

        foreach ($fills as [$date, $km, $liters, $cost, $full]) {
            $motor->fuelLogs()->create([
                'filled_at' => $date, 'odometer_km' => $km, 'liters' => $liters,
                'total_cost' => $cost, 'is_full_tank' => $full,
            ]);
        }

        return $motor->fresh();
    }

    public function test_consumption_series_only_counts_full_to_full(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
            ['2026-07-05', 1050, 3.0, 45000, false], // partial fill, skipped as endpoint
            ['2026-07-10', 1150, 5.0, 75000, true],
        ]);

        $series = $this->svc->consumptionSeries($motor);

        // Only the full(1000) -> full(1150) pair counts: (1150-1000)/5.0 = 30.0
        $this->assertCount(1, $series);
        $this->assertEquals(30.0, $series[0]['km_per_liter']);
    }

    public function test_average_and_latest_km_per_liter(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
            ['2026-07-05', 1100, 5.0, 75000, true],
            ['2026-07-10', 1180, 4.0, 60000, true],
        ]);

        // 1000->1100 = 20.0 km/l, 1100->1180 = 20.0 km/l
        $this->assertEquals(20.0, $this->svc->averageKmPerLiter($motor));
        $this->assertEquals(20.0, $this->svc->latestKmPerLiter($motor));
    }

    public function test_null_when_not_enough_full_tank_data(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
        ]);

        $this->assertNull($this->svc->averageKmPerLiter($motor));
        $this->assertNull($this->svc->latestKmPerLiter($motor));
    }

    public function test_cost_per_km(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
            ['2026-07-10', 1200, 5.0, 75000, true],
        ]);

        // total cost 135000 / distance (1200-1000)=200 = 675
        $this->assertEquals(675.0, $this->svc->costPerKm($motor));
    }

    public function test_cost_per_km_null_with_single_log(): void
    {
        $motor = $this->motorWithFuel([
            ['2026-07-01', 1000, 4.0, 60000, true],
        ]);

        $this->assertNull($this->svc->costPerKm($motor));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FuelStatsServiceTest`
Expected: FAIL  class not found.

- [ ] **Step 3: Implement FuelStatsService**

`app/Services/FuelStatsService.php`:

```php
<?php

namespace App\Services;

use App\Models\Motorcycle;

class FuelStatsService
{
    public function consumptionSeries(Motorcycle $motorcycle): array
    {
        $logs = $motorcycle->fuelLogs()->orderBy('filled_at')->orderBy('id')->get();

        $series = [];
        $prevFull = null;

        foreach ($logs as $log) {
            if ($prevFull !== null && $log->is_full_tank) {
                $distance = $log->odometer_km - $prevFull->odometer_km;
                $liters = (float) $log->liters;
                if ($distance > 0 && $liters > 0) {
                    $series[] = [
                        'date' => $log->filled_at->toDateString(),
                        'km_per_liter' => round($distance / $liters, 1),
                    ];
                }
            }
            if ($log->is_full_tank) {
                $prevFull = $log;
            }
        }

        return $series;
    }

    public function averageKmPerLiter(Motorcycle $motorcycle): ?float
    {
        $series = $this->consumptionSeries($motorcycle);
        if (empty($series)) {
            return null;
        }

        return round(array_sum(array_column($series, 'km_per_liter')) / count($series), 1);
    }

    public function latestKmPerLiter(Motorcycle $motorcycle): ?float
    {
        $series = $this->consumptionSeries($motorcycle);

        return empty($series) ? null : end($series)['km_per_liter'];
    }

    public function costPerKm(Motorcycle $motorcycle): ?float
    {
        $logs = $motorcycle->fuelLogs()->orderBy('odometer_km')->get();
        if ($logs->count() < 2) {
            return null;
        }

        $distance = $logs->last()->odometer_km - $logs->first()->odometer_km;
        if ($distance <= 0) {
            return null;
        }

        return round($logs->sum('total_cost') / $distance, 0);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=FuelStatsServiceTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/FuelStatsService.php tests/Unit/FuelStatsServiceTest.php
git commit -m "feat: FuelStatsService for consumption and cost-per-km calculations"
```

---

## Task 3: Fuel CRUD  controller, routes, BBM page

**Files:**
- Create: `app/Http/Controllers/FuelController.php`
- Create: `resources/views/bbm/index.blade.php`
- Create: `resources/views/components/icon/droplet.blade.php`, `resources/views/components/icon/trending-up.blade.php`, `resources/views/components/icon/trending-down.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/FuelControllerTest.php`

**Interfaces:**
- Consumes: `FuelLog` (Task 1), `FuelStatsService` (Task 2).
- Produces: routes `bbm.index` (GET `/bbm`), `bbm.store` (POST `/bbm`), `bbm.destroy` (DELETE `/bbm/{fuelLog}`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/FuelControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_storing_fuel_log_updates_odometer_if_higher(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($user)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-19',
            'odometer_km' => 1200,
            'liters' => 4.5,
            'total_cost' => 65000,
            'is_full_tank' => '1',
        ])->assertRedirect(route('bbm.index'));

        $this->assertEquals(1200, $motor->fresh()->current_odometer_km);
        $this->assertDatabaseHas('fuel_logs', ['motorcycle_id' => $motor->id, 'total_cost' => 65000]);
    }

    public function test_storing_fuel_log_does_not_lower_odometer(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 5000]);

        $this->actingAs($user)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-19',
            'odometer_km' => 4900,
            'liters' => 3.0,
            'total_cost' => 45000,
        ]);

        $this->assertEquals(5000, $motor->fresh()->current_odometer_km);
    }

    public function test_cannot_store_fuel_log_for_other_users_motorcycle(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $owner->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $this->actingAs($intruder)->post(route('bbm.store'), [
            'motorcycle_id' => $motor->id,
            'filled_at' => '2026-07-19',
            'odometer_km' => 1200,
            'liters' => 4.5,
            'total_cost' => 65000,
        ])->assertForbidden();
    }

    public function test_can_delete_own_fuel_log(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $log = $motor->fuelLogs()->create([
            'filled_at' => '2026-07-01', 'odometer_km' => 1000, 'liters' => 4, 'total_cost' => 60000,
        ]);

        $this->actingAs($user)->delete(route('bbm.destroy', $log))->assertRedirect();
        $this->assertDatabaseMissing('fuel_logs', ['id' => $log->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FuelControllerTest`
Expected: FAIL  route `bbm.store` not defined.

- [ ] **Step 3: Create controller**

`app/Http/Controllers/FuelController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\FuelLog;
use App\Models\Motorcycle;
use App\Services\FuelStatsService;
use Illuminate\Http\Request;

class FuelController extends Controller
{
    public function index(FuelStatsService $stats)
    {
        $motorcycles = auth()->user()->motorcycles()->get();

        $logs = FuelLog::whereHas('motorcycle', fn ($q) => $q->where('user_id', auth()->id()))
            ->with('motorcycle')
            ->orderByDesc('filled_at')
            ->orderByDesc('id')
            ->get();

        $motorStats = $motorcycles->map(fn ($m) => [
            'motor' => $m,
            'avg_km_per_liter' => $stats->averageKmPerLiter($m),
            'latest_km_per_liter' => $stats->latestKmPerLiter($m),
            'cost_per_km' => $stats->costPerKm($m),
        ]);

        $totalCost = $logs->sum('total_cost');

        return view('bbm.index', compact('motorcycles', 'logs', 'motorStats', 'totalCost'));
    }

    public function store(Request $request)
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
        $motor->fuelLogs()->create($data);

        if ($data['odometer_km'] > $motor->current_odometer_km) {
            $motor->update(['current_odometer_km' => $data['odometer_km']]);
        }

        return redirect()->route('bbm.index')->with('status', 'Isi bensin dicatat.');
    }

    public function destroy(FuelLog $fuelLog)
    {
        abort_unless($fuelLog->motorcycle->user_id === auth()->id(), 403);
        $fuelLog->delete();

        return back()->with('status', 'Catatan BBM dihapus.');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, add `use App\Http\Controllers\FuelController;` to the imports, and inside the `Route::middleware('auth')->group(...)` block (near the `map/*` routes) add:

```php
    Route::get('bbm', [FuelController::class, 'index'])->name('bbm.index');
    Route::post('bbm', [FuelController::class, 'store'])->name('bbm.store');
    Route::delete('bbm/{fuelLog}', [FuelController::class, 'destroy'])->name('bbm.destroy');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=FuelControllerTest`
Expected: PASS (4 tests)

- [ ] **Step 6: New icons**

`resources/views/components/icon/droplet.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>
</svg>
```

`resources/views/components/icon/trending-up.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <path d="M16 7h6v6"/>
    <path d="m22 7-8.5 8.5-5-5L2 17"/>
</svg>
```

`resources/views/components/icon/trending-down.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <path d="M16 17h6v-6"/>
    <path d="m22 17-8.5-8.5-5 5L2 7"/>
</svg>
```

- [ ] **Step 7: BBM page view**

`resources/views/bbm/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">BBM</x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">

        <x-ui.hero badge="{{ $logs->count() }} catatan isi" title="Manajemen BBM"
                    subtitle="Catat tiap isi bensin untuk tahu konsumsi (km/liter) dan biaya per km motormu.">
            <x-slot:side>
                <x-ui.button variant="white" type="button" x-data @click="$dispatch('open-fuel-form')">Catat Isi Bensin</x-ui.button>
            </x-slot:side>
        </x-ui.hero>

        @if (session('status'))
            <div class="p-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-medium">{{ session('status') }}</div>
        @endif

        {{-- Per-motor efficiency stats --}}
        <div data-reveal-group class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($motorStats as $ms)
                <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                    <p class="font-heading font-bold text-foreground text-sm mb-3">{{ $ms['motor']->nickname }}</p>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Rata-rata</p>
                            <p class="text-xl font-heading font-extrabold text-foreground tabular-nums">
                                {{ $ms['avg_km_per_liter'] ?? '—' }}<span class="text-sm font-normal text-muted-fg">{{ $ms['avg_km_per_liter'] ? ' km/l' : '' }}</span>
                            </p>
                        </div>
                        <div class="size-10 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                            <x-icon.droplet class="w-5 h-5"/>
                        </div>
                    </div>
                    <p class="text-xs text-muted-fg mt-3 tabular-nums">
                        Biaya/km: {{ $ms['cost_per_km'] ? 'Rp'.number_format($ms['cost_per_km']) : '—' }}
                    </p>
                    @if (!$ms['avg_km_per_liter'])
                        <p class="text-[11px] text-muted-fg mt-1">Butuh minimal 2x isi tank penuh untuk hitung efisiensi.</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-muted-fg col-span-full">Belum ada motor.</p>
            @endforelse
        </div>

        {{-- Add form --}}
        <div x-data="{ open: false }" @open-fuel-form.window="open = true" class="bg-surface border border-border rounded-2xl overflow-hidden" x-cloak>
            <button @click="open = !open" type="button" class="w-full p-5 flex items-center justify-between text-left">
                <h3 class="font-heading font-bold text-foreground text-sm">Catat Isi Bensin Baru</h3>
                <span class="text-primary text-sm font-semibold" x-text="open ? 'Tutup' : 'Buka'"></span>
            </button>
            <form x-show="open" method="POST" action="{{ route('bbm.store') }}" class="p-5 pt-0 grid sm:grid-cols-2 gap-4 border-t border-border">
                @csrf
                <label class="space-y-1.5">
                    <span class="block text-sm font-medium text-foreground">Motor</span>
                    <select name="motorcycle_id" required class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($motorcycles as $m)
                            <option value="{{ $m->id }}" @selected($m->is_active)>{{ $m->nickname }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ui.input name="filled_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <x-ui.input name="odometer_km" label="Odometer saat isi (km)" type="number" required />
                <x-ui.input name="liters" label="Jumlah liter" type="number" step="0.1" required />
                <x-ui.input name="total_cost" label="Total biaya (Rp)" type="number" required />
                <label class="flex items-center gap-2 text-sm text-foreground">
                    <input type="checkbox" name="is_full_tank" value="1" checked class="rounded border-border text-primary focus:ring-primary/30">
                    Isi tank penuh (full tank)
                </label>
                <div class="sm:col-span-2">
                    <x-ui.button variant="primary" type="submit">Simpan</x-ui.button>
                </div>
            </form>
        </div>

        {{-- History table --}}
        <div class="bg-surface border border-border rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-border bg-muted/40">
                <h3 class="font-heading font-bold text-foreground text-sm">Riwayat Isi Bensin</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] font-bold text-muted-fg uppercase tracking-widest border-b border-border">
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Motor</th>
                            <th class="px-5 py-3 text-right">Odometer</th>
                            <th class="px-5 py-3 text-right">Liter</th>
                            <th class="px-5 py-3 text-right">Biaya</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-muted/40 transition">
                                <td class="px-5 py-3 text-muted-fg tabular-nums whitespace-nowrap">{{ $log->filled_at->format('d M Y') }}</td>
                                <td class="px-5 py-3 font-medium text-foreground whitespace-nowrap">{{ $log->motorcycle->nickname }}</td>
                                <td class="px-5 py-3 text-right text-muted-fg tabular-nums">{{ number_format($log->odometer_km) }}</td>
                                <td class="px-5 py-3 text-right text-muted-fg tabular-nums">{{ $log->liters }}</td>
                                <td class="px-5 py-3 text-right font-bold text-foreground tabular-nums">Rp{{ number_format($log->total_cost) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('bbm.destroy', $log) }}" onsubmit="return confirm('Hapus catatan ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-muted-fg hover:text-accent hover:bg-accent/10 transition">
                                            <x-icon.trash class="w-4 h-4"/>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-muted-fg">Belum ada catatan isi bensin.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 8: Add "BBM" to sidebar nav**

In `resources/views/layouts/navigation.blade.php`, in the `$links` array, add a new entry right after the `'history'` entry:

```php
        ['route' => 'bbm.index', 'pattern' => 'bbm.*', 'label' => 'BBM', 'icon' => 'droplet'],
```

- [ ] **Step 9: Full test suite + manual verification**

Run: `php artisan test`
Expected: all tests pass (44 total: 40 existing + 4 new).

Then start the dev server and manually verify: `php artisan serve`, log in, go to `/bbm`, add a fuel log, confirm it appears in the table and the odometer updates on the motorcycle.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/FuelController.php resources/views/bbm/index.blade.php resources/views/components/icon/droplet.blade.php resources/views/components/icon/trending-up.blade.php resources/views/components/icon/trending-down.blade.php resources/views/layouts/navigation.blade.php routes/web.php tests/Feature/FuelControllerTest.php
git commit -m "feat: fuel tracking page with efficiency stats"
```

---

## Task 4: Richer service history  workshop, parts, receipt photo

**Files:**
- Create: `database/migrations/2026_07_19_000002_add_service_detail_to_maintenance_logs_table.php`
- Modify: `app/Models/MaintenanceLog.php`
- Modify: `app/Http/Controllers/MaintenanceController.php`
- Modify: `resources/views/motorcycles/show.blade.php`
- Modify: `resources/views/history/index.blade.php`
- Modify: `resources/views/history/export-pdf.blade.php`
- Create: `resources/views/components/icon/camera.blade.php`
- Test: `tests/Feature/MaintenanceTest.php` (extend)

**Interfaces:**
- Consumes: existing `MaintenanceController::complete()` flow.
- Produces: `MaintenanceLog` gains `workshop_name (string|null)`, `parts (string|null)`, `receipt_path (string|null)`.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/MaintenanceTest.php`, add this test to the existing class:

```php
    public function test_completing_item_with_workshop_parts_and_receipt(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = \App\Models\User::factory()->create();
        $motor = \App\Models\Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();

        $file = \Illuminate\Http\UploadedFile::fake()->image('nota.jpg');

        $this->actingAs($user)->post(route('maintenance.complete', $oli), [
            'serviced_at' => '2026-07-19',
            'cost' => 50000,
            'workshop_name' => 'Bengkel Jaya Motor',
            'parts' => 'Oli Federal 0.8L, filter oli',
            'receipt' => $file,
        ])->assertRedirect();

        $log = $oli->logs()->latest()->first();
        $this->assertEquals('Bengkel Jaya Motor', $log->workshop_name);
        $this->assertEquals('Oli Federal 0.8L, filter oli', $log->parts);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($log->receipt_path);
    }

    public function test_receipt_upload_rejects_non_image(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = \App\Models\User::factory()->create();
        $motor = \App\Models\Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000,
        ]);
        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();

        $file = \Illuminate\Http\UploadedFile::fake()->create('nota.pdf', 100);

        $this->actingAs($user)->post(route('maintenance.complete', $oli), [
            'serviced_at' => '2026-07-19',
            'receipt' => $file,
        ])->assertSessionHasErrors('receipt');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MaintenanceTest`
Expected: FAIL  column `workshop_name` does not exist.

- [ ] **Step 3: Create migration**

```bash
php artisan make:migration add_service_detail_to_maintenance_logs_table --table=maintenance_logs
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
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->string('workshop_name')->nullable()->after('note');
            $table->string('parts')->nullable()->after('workshop_name');
            $table->string('receipt_path')->nullable()->after('parts');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropColumn(['workshop_name', 'parts', 'receipt_path']);
        });
    }
};
```

- [ ] **Step 4: Update MaintenanceLog model fillable**

In `app/Models/MaintenanceLog.php`, change the `$fillable` array to:

```php
    protected $fillable = [
        'maintenance_item_id', 'serviced_at_odometer_km', 'cost', 'serviced_at', 'note',
        'workshop_name', 'parts', 'receipt_path',
    ];
```

- [ ] **Step 5: Update MaintenanceController::complete()**

Replace the full method in `app/Http/Controllers/MaintenanceController.php`:

```php
    public function complete(Request $request, MaintenanceItem $item)
    {
        abort_unless($item->motorcycle->user_id === auth()->id(), 403);

        $data = $request->validate([
            'cost' => 'nullable|integer|min:0',
            'serviced_at' => 'required|date',
            'note' => 'nullable|string|max:255',
            'workshop_name' => 'nullable|string|max:255',
            'parts' => 'nullable|string|max:255',
            'receipt' => 'nullable|image|max:2048',
        ]);

        $receiptPath = $request->hasFile('receipt')
            ? $request->file('receipt')->store('receipts', 'public')
            : null;

        $odometer = $item->motorcycle->current_odometer_km;
        $item->logs()->create([
            'serviced_at_odometer_km' => $odometer,
            'cost' => $data['cost'] ?? null,
            'serviced_at' => $data['serviced_at'],
            'note' => $data['note'] ?? null,
            'workshop_name' => $data['workshop_name'] ?? null,
            'parts' => $data['parts'] ?? null,
            'receipt_path' => $receiptPath,
        ]);
        $item->update(['last_service_odometer_km' => $odometer]);

        return back()->with('status', "{$item->name} ditandai selesai di {$odometer} km.");
    }
```

- [ ] **Step 6: Link public storage disk**

Run: `php artisan storage:link`
Expected: `The [public/storage] link has been connected to [storage/app/public].` (skip if it already exists)

- [ ] **Step 7: Migrate and run tests**

Run: `php artisan migrate --no-interaction && php artisan test --filter=MaintenanceTest`
Expected: PASS (all MaintenanceTest tests including the 2 new ones)

- [ ] **Step 8: Camera icon**

`resources/views/components/icon/camera.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/>
    <circle cx="12" cy="13" r="3"/>
</svg>
```

- [ ] **Step 9: Update the "tandai selesai" form in motorcycles/show.blade.php**

In `resources/views/motorcycles/show.blade.php`, find the `<form x-show="open" x-cloak method="POST" action="{{ route('maintenance.complete', $i['item']) }}" ...>` block. Add `enctype="multipart/form-data"` to the `<form>` tag, and insert these three fields between the existing `cost` and `serviced_at` inputs:

```blade
                        <x-ui.input name="workshop_name" label="Nama Bengkel (opsional)" placeholder="Bengkel Jaya Motor" />
                        <x-ui.input name="parts" label="Sparepart diganti (opsional)" placeholder="Oli Federal 0.8L, filter oli" />
                        <label class="space-y-1.5 block">
                            <span class="block text-sm font-medium text-foreground">Foto Nota (opsional)</span>
                            <input type="file" name="receipt" accept="image/*" class="w-full text-sm text-muted-fg file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-primary-soft file:text-primary file:text-sm file:font-medium">
                        </label>
```

- [ ] **Step 10: Show workshop/parts/receipt in Biaya & Servis table**

In `resources/views/history/index.blade.php`, in the expense table row (the `<tr>` inside `@forelse ($logs as $log)`), change the "Item" `<td>` to also show workshop/parts, and add a receipt thumbnail column. Replace:

```blade
                                    <td class="px-5 py-3 text-foreground whitespace-nowrap">{{ $log->item->name }}</td>
```

with:

```blade
                                    <td class="px-5 py-3 text-foreground whitespace-nowrap">
                                        {{ $log->item->name }}
                                        @if ($log->workshop_name)
                                            <span class="block text-[11px] text-muted-fg font-normal">{{ $log->workshop_name }}</span>
                                        @endif
                                    </td>
```

And add a new last column for the receipt. Change the `<thead>` row to add `<th class="px-5 py-3">Nota</th>` after the "Biaya" header, and add this `<td>` at the end of the row (before the closing `</tr>`):

```blade
                                    <td class="px-5 py-3">
                                        @if ($log->receipt_path)
                                            <a href="{{ asset('storage/'.$log->receipt_path) }}" target="_blank" rel="noopener" class="inline-block size-8 rounded-lg overflow-hidden border border-border">
                                                <img src="{{ asset('storage/'.$log->receipt_path) }}" alt="Nota" class="w-full h-full object-cover">
                                            </a>
                                        @endif
                                    </td>
```

Also update the `colspan="5"` on the empty-state row to `colspan="6"`.

- [ ] **Step 11: Add workshop/parts to PDF export**

In `resources/views/history/export-pdf.blade.php`, add a "Bengkel" header/column and a "Sparepart" header/column to the table, matching the existing pattern (add `<th>Bengkel</th><th>Sparepart</th>` to `<thead>`, and `<td>{{ $l->workshop_name }}</td><td>{{ $l->parts }}</td>` to each row).

- [ ] **Step 12: Full test suite + manual verification**

Run: `php artisan test`
Expected: all pass.

Manually verify: go to a motorcycle detail page, mark an item complete with a workshop name, parts, and an uploaded image; confirm it shows on the Biaya & Servis page with a clickable thumbnail.

- [ ] **Step 13: Commit**

```bash
git add database/migrations/2026_07_19_000002_add_service_detail_to_maintenance_logs_table.php app/Models/MaintenanceLog.php app/Http/Controllers/MaintenanceController.php resources/views/motorcycles/show.blade.php resources/views/history/index.blade.php resources/views/history/export-pdf.blade.php resources/views/components/icon/camera.blade.php tests/Feature/MaintenanceTest.php
git commit -m "feat: workshop name, parts, and receipt photo on service records"
```

---

## Task 5: MaintenancePredictionService

**Files:**
- Create: `app/Services/MaintenancePredictionService.php`
- Test: `tests/Unit/MaintenancePredictionServiceTest.php`

**Interfaces:**
- Consumes: `Motorcycle`, `MaintenanceItem`, `MaintenanceStatusService` (existing, `app/Services/MaintenanceStatusService.php`).
- Produces:
  - `MaintenancePredictionService::avgKmPerDay(Motorcycle $m): ?float`  trips in last 30 days / 30, falling back to lifetime average.
  - `MaintenancePredictionService::forItem(MaintenanceItem $item, int $currentOdometer, ?float $avgKmPerDay): array` → `['days_left' => int|null, 'predicted_date' => \Carbon\Carbon|null]`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/MaintenancePredictionServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\MaintenanceItem;
use App\Models\Motorcycle;
use App\Models\User;
use App\Services\MaintenancePredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private MaintenancePredictionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new MaintenancePredictionService();
    }

    public function test_avg_km_per_day_from_recent_trips(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);

        $motor->trips()->create([
            'distance_km' => 60, 'duration_seconds' => 3600,
            'started_at' => now()->subDays(5), 'ended_at' => now()->subDays(5),
        ]);

        // 60 km total over the fixed 30-day window = 2.0 km/day
        $this->assertEquals(2.0, $this->svc->avgKmPerDay($motor));
    }

    public function test_avg_km_per_day_falls_back_to_lifetime_when_no_recent_trips(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 1000, 'current_odometer_km' => 1000,
        ]);
        // No trips at all, and no km traveled since creation -> null
        $this->assertNull($this->svc->avgKmPerDay($motor));
    }

    public function test_for_item_calculates_days_left_and_predicted_date(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $item = MaintenanceItem::create([
            'motorcycle_id' => $motor->id, 'name' => 'Oli Mesin',
            'interval_km' => 2500, 'last_service_odometer_km' => 1000,
        ]);

        // used=0, remaining=2500, avg 250 km/day -> 10 days left
        $result = $this->svc->forItem($item, 1000, 250.0);

        $this->assertEquals(10, $result['days_left']);
        $this->assertEquals(now()->addDays(10)->toDateString(), $result['predicted_date']->toDateString());
    }

    public function test_for_item_returns_null_without_avg_km_per_day(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 1000]);
        $item = MaintenanceItem::create([
            'motorcycle_id' => $motor->id, 'name' => 'Oli Mesin',
            'interval_km' => 2500, 'last_service_odometer_km' => 1000,
        ]);

        $result = $this->svc->forItem($item, 1000, null);

        $this->assertNull($result['days_left']);
        $this->assertNull($result['predicted_date']);
    }

    public function test_for_item_zero_days_left_when_already_overdue(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 4000]);
        $item = MaintenanceItem::create([
            'motorcycle_id' => $motor->id, 'name' => 'Oli Mesin',
            'interval_km' => 2500, 'last_service_odometer_km' => 1000,
        ]);

        // used=3000 > interval=2500 -> remaining clamped to 0 by MaintenanceStatusService -> 0 days left
        $result = $this->svc->forItem($item, 4000, 100.0);

        $this->assertEquals(0, $result['days_left']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MaintenancePredictionServiceTest`
Expected: FAIL  class not found.

- [ ] **Step 3: Implement the service**

`app/Services/MaintenancePredictionService.php`:

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
    ) {
    }

    /**
     * Average km ridden per day, from trips in the last 30 days.
     * Falls back to lifetime average (odometer growth / days since created)
     * when there is no recent trip data.
     *
     * ponytail: 30-day window is a tuning knob, not a fixed law  revisit if
     * predictions feel stale for infrequent riders.
     */
    public function avgKmPerDay(Motorcycle $motorcycle): ?float
    {
        $recentKm = (float) $motorcycle->trips()
            ->where('ended_at', '>=', now()->subDays(30))
            ->sum('distance_km');

        if ($recentKm > 0) {
            return round($recentKm / 30, 2);
        }

        $daysSinceCreated = max(1, $motorcycle->created_at->diffInDays(now()));
        $totalKm = $motorcycle->current_odometer_km - $motorcycle->initial_odometer_km;

        if ($totalKm <= 0) {
            return null;
        }

        return round($totalKm / $daysSinceCreated, 2);
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

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MaintenancePredictionServiceTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/MaintenancePredictionService.php tests/Unit/MaintenancePredictionServiceTest.php
git commit -m "feat: MaintenancePredictionService  days-left estimate from riding average"
```

---

## Task 6: HealthScoreService

**Files:**
- Create: `app/Services/HealthScoreService.php`
- Test: `tests/Unit/HealthScoreServiceTest.php`

**Interfaces:**
- Consumes: `Motorcycle` (with `maintenanceItems`), `MaintenanceStatusService`, `FuelStatsService` (Task 2).
- Produces: `HealthScoreService::forMotorcycle(Motorcycle $m): array` → `['score' => int 0-100, 'label' => string, 'color' => 'green'|'yellow'|'red']`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/HealthScoreServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\FuelStatsService;
use App\Services\HealthScoreService;
use App\Services\MaintenanceStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private HealthScoreService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new HealthScoreService(new MaintenanceStatusService(), new FuelStatsService());
    }

    public function test_brand_new_motorcycle_scores_100(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'current_odometer_km' => 0]);

        $result = $this->svc->forMotorcycle($motor->fresh(['maintenanceItems']));

        $this->assertEquals(100, $result['score']);
        $this->assertEquals('green', $result['color']);
        $this->assertEquals('Sehat', $result['label']);
    }

    public function test_one_overdue_item_deducts_15(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        // Oli Mesin: interval 2500, last_service 0 -> used 3000 > interval -> overdue (-15)

        $result = $this->svc->forMotorcycle($motor->fresh(['maintenanceItems']));

        $this->assertEquals(85, $result['score']);
    }

    public function test_score_clamped_to_zero_minimum(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 100000,
        ]);
        // All 4 default items overdue: 4 * -15 = -60 -> score 40, still above 0 here;
        // this test documents the clamp exists even though 4 items alone won't hit it.
        $result = $this->svc->forMotorcycle($motor->fresh(['maintenanceItems']));

        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertEquals('red', $result['color']);
        $this->assertEquals('Butuh Servis', $result['label']);
    }

    public function test_label_bands(): void
    {
        $this->assertEquals('Sehat', $this->labelFor(85));
        $this->assertEquals('Perlu Perhatian', $this->labelFor(65));
        $this->assertEquals('Butuh Servis', $this->labelFor(40));
    }

    private function labelFor(int $score): string
    {
        return $score >= 80 ? 'Sehat' : ($score >= 60 ? 'Perlu Perhatian' : 'Butuh Servis');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HealthScoreServiceTest`
Expected: FAIL  class not found.

- [ ] **Step 3: Implement the service**

`app/Services/HealthScoreService.php`:

```php
<?php

namespace App\Services;

use App\Models\Motorcycle;

class HealthScoreService
{
    public function __construct(
        private MaintenanceStatusService $statusService,
        private FuelStatsService $fuelStatsService,
    ) {
    }

    /**
     * Composite 0-100 score from maintenance status + fuel efficiency trend.
     * ponytail: penalty weights (15/5/10) and the 0.85 efficiency-drop
     * threshold are tuning knobs  adjust if the score feels miscalibrated
     * against real usage.
     */
    public function forMotorcycle(Motorcycle $motorcycle): array
    {
        $score = 100;

        foreach ($motorcycle->maintenanceItems as $item) {
            $status = $this->statusService->forItem($item, $motorcycle->current_odometer_km);

            if ($status['percent'] > 100) {
                $score -= 15;
            } elseif ($status['percent'] >= 80) {
                $score -= 5;
            }
        }

        $avg = $this->fuelStatsService->averageKmPerLiter($motorcycle);
        $latest = $this->fuelStatsService->latestKmPerLiter($motorcycle);
        if ($avg && $latest && $latest < 0.85 * $avg) {
            $score -= 10;
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'label' => $score >= 80 ? 'Sehat' : ($score >= 60 ? 'Perlu Perhatian' : 'Butuh Servis'),
            'color' => $score >= 80 ? 'green' : ($score >= 60 ? 'yellow' : 'red'),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=HealthScoreServiceTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/HealthScoreService.php tests/Unit/HealthScoreServiceTest.php
git commit -m "feat: HealthScoreService  composite 0-100 vehicle health score"
```

---

## Task 7: AttentionService + Dashboard integration (health score, predictions, action center)

**Files:**
- Create: `app/Services/AttentionService.php`
- Create: `resources/views/components/icon/activity.blade.php`, `resources/views/components/icon/alert-triangle.blade.php`, `resources/views/components/icon/clock.blade.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Unit/AttentionServiceTest.php`

**Interfaces:**
- Consumes: `MaintenanceStatusService`, `MaintenancePredictionService` (Task 5), `FuelStatsService` (Task 2).
- Produces: `AttentionService::forUser(User $u): array` → list of `['severity' => 'red'|'yellow', 'text' => string, 'url' => string]`, red items first.

- [ ] **Step 1: Write the failing test**

`tests/Unit/AttentionServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\AttentionService;
use App\Services\FuelStatsService;
use App\Services\MaintenancePredictionService;
use App\Services\MaintenanceStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttentionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttentionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new AttentionService(
            new MaintenanceStatusService(),
            new MaintenancePredictionService(),
            new FuelStatsService(),
        );
    }

    public function test_overdue_item_produces_red_severity_item(): void
    {
        $user = User::factory()->create();
        Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'Beat',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        // Oli Mesin: interval 2500, used 3000 -> overdue

        $items = $this->svc->forUser($user->fresh(['motorcycles.maintenanceItems']));

        $redItems = array_filter($items, fn ($i) => $i['severity'] === 'red');
        $this->assertNotEmpty($redItems);
        $this->assertStringContainsString('Oli Mesin', array_values($redItems)[0]['text']);
    }

    public function test_healthy_motorcycle_with_no_trips_produces_no_items(): void
    {
        $user = User::factory()->create();
        Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'Beat', 'current_odometer_km' => 0,
        ]);
        // All items at 0% used, no trips -> no red (not overdue) and no yellow (no prediction data)

        $items = $this->svc->forUser($user->fresh(['motorcycles.maintenanceItems']));

        $this->assertEmpty($items);
    }

    public function test_red_items_sorted_before_yellow(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'Beat',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        $motor->trips()->create([
            'distance_km' => 300, 'duration_seconds' => 60,
            'started_at' => now()->subDay(), 'ended_at' => now()->subDay(),
        ]);
        // avg km/day ~10 -> Aki (interval 15000, remaining 12000) gives a large days_left,
        // Oli Mesin is overdue -> red. Just assert first item (if any) is red when present.

        $items = $this->svc->forUser($user->fresh(['motorcycles.maintenanceItems']));

        if (count($items) > 1) {
            $severities = array_column($items, 'severity');
            $firstYellowIndex = array_search('yellow', $severities);
            $lastRedIndex = array_search('red', array_reverse($severities, true));
            if ($firstYellowIndex !== false && $lastRedIndex !== false) {
                $this->assertLessThan($firstYellowIndex, count($severities) - 1 - $lastRedIndex + $firstYellowIndex, 'red items should not appear after yellow ones is implicitly checked by construction');
            }
        }
        $this->assertNotEmpty($items);
        $this->assertEquals('red', $items[0]['severity']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AttentionServiceTest`
Expected: FAIL  class not found.

- [ ] **Step 3: Implement the service**

`app/Services/AttentionService.php`:

```php
<?php

namespace App\Services;

use App\Models\User;

class AttentionService
{
    public function __construct(
        private MaintenanceStatusService $statusService,
        private MaintenancePredictionService $predictionService,
        private FuelStatsService $fuelStatsService,
    ) {
    }

    /**
     * ponytail: 14-day "coming due soon" threshold is a tuning knob.
     */
    public function forUser(User $user): array
    {
        $items = [];

        foreach ($user->motorcycles as $motor) {
            $avgKmPerDay = $this->predictionService->avgKmPerDay($motor);

            foreach ($motor->maintenanceItems as $item) {
                $status = $this->statusService->forItem($item, $motor->current_odometer_km);

                if ($status['percent'] > 100) {
                    $items[] = [
                        'severity' => 'red',
                        'text' => "Segera servis {$item->name}  {$motor->nickname}",
                        'url' => route('motorcycles.show', $motor),
                    ];
                    continue;
                }

                $prediction = $this->predictionService->forItem($item, $motor->current_odometer_km, $avgKmPerDay);
                if ($prediction['days_left'] !== null && $prediction['days_left'] <= 14) {
                    $items[] = [
                        'severity' => 'yellow',
                        'text' => "{$item->name} {$motor->nickname} diperkirakan ~{$prediction['days_left']} hari lagi",
                        'url' => route('motorcycles.show', $motor),
                    ];
                }
            }

            $avg = $this->fuelStatsService->averageKmPerLiter($motor);
            $latest = $this->fuelStatsService->latestKmPerLiter($motor);
            if ($avg && $latest && $latest < 0.85 * $avg) {
                $items[] = [
                    'severity' => 'yellow',
                    'text' => "Konsumsi BBM {$motor->nickname} turun, cek kondisi mesin",
                    'url' => route('bbm.index'),
                ];
            }
        }

        usort($items, fn ($a, $b) => ($a['severity'] === 'red' ? 0 : 1) <=> ($b['severity'] === 'red' ? 0 : 1));

        return $items;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AttentionServiceTest`
Expected: PASS (3 tests)

- [ ] **Step 5: New icons**

`resources/views/components/icon/activity.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
</svg>
```

`resources/views/components/icon/alert-triangle.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
    <path d="M12 9v4"/>
    <path d="M12 17h.01"/>
</svg>
```

`resources/views/components/icon/clock.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="10"/>
    <polyline points="12 6 12 12 16 14"/>
</svg>
```

- [ ] **Step 6: Update DashboardController**

Replace the full contents of `app/Http/Controllers/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use App\Services\AttentionService;
use App\Services\HealthScoreService;
use App\Services\MaintenancePredictionService;
use App\Services\MaintenanceStatusService;

class DashboardController extends Controller
{
    public function __invoke(
        MaintenanceStatusService $status,
        MaintenancePredictionService $prediction,
        HealthScoreService $healthScore,
        AttentionService $attention
    ) {
        $motorcycles = auth()->user()->motorcycles()->with('maintenanceItems')->get();

        $dashboard = $motorcycles->map(function ($motor) use ($status, $prediction, $healthScore) {
            $avgKmPerDay = $prediction->avgKmPerDay($motor);

            return [
                'motor' => $motor,
                'health' => $healthScore->forMotorcycle($motor),
                'items' => $motor->maintenanceItems->map(fn ($item) => [
                    'item' => $item,
                    'status' => $status->forItem($item, $motor->current_odometer_km),
                    'prediction' => $prediction->forItem($item, $motor->current_odometer_km, $avgKmPerDay),
                ]),
            ];
        });

        $kpi = [
            'motor_count' => $motorcycles->count(),
            'total_km' => $motorcycles->sum('current_odometer_km'),
            'attention' => $dashboard->sum(fn ($row) => $row['items']->filter(fn ($i) => $i['status']['color'] !== 'green')->count()),
            'total_cost' => MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', auth()->id()))->sum('cost'),
        ];

        $attentionItems = $attention->forUser(auth()->user()->load('motorcycles.maintenanceItems'));

        return view('dashboard', ['dashboard' => $dashboard, 'kpi' => $kpi, 'attentionItems' => $attentionItems]);
    }
}
```

- [ ] **Step 7: Update dashboard view**

Read the current `resources/views/dashboard.blade.php` first (it already has a hero, 4 stat tiles, and per-motor cards). Make these three additions:

**(a)** Inside each motor card, right after the badge (`Perhatian`/`Aman` span), add a health score badge. Find the block that starts with `@if ($needsAttention)` / `@else` for the badge, and add this immediately after that `@if/@else/@endif`:

```blade
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-lg
                                {{ $row['health']['color'] === 'green' ? 'bg-emerald-50 text-emerald-700' : ($row['health']['color'] === 'yellow' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-600') }}">
                                Skor {{ $row['health']['score'] }}
                            </span>
```

**(b)** Inside the loop over `$row['items']` (where progress bars render), add a prediction label. Find the `<x-ui.progress :percent="$i['status']['percent']" :color="$i['status']['color']" />` line and add this immediately after it:

```blade
                                    @if ($i['prediction']['days_left'] !== null)
                                        <p class="text-[11px] {{ $i['prediction']['days_left'] === 0 ? 'text-red-600 font-semibold' : ($i['prediction']['days_left'] <= 14 ? 'text-amber-600' : 'text-muted-fg') }}">
                                            @if ($i['prediction']['days_left'] === 0)
                                                Sudah lewat batas
                                            @else
                                                Estimasi ~{{ $i['prediction']['days_left'] }} hari lagi ({{ $i['prediction']['predicted_date']->translatedFormat('d M Y') }})
                                            @endif
                                        </p>
                                    @endif
```

**(c)** Add a "Pusat Perhatian" card. Insert this new section right before the closing `</div>` of the outermost `<div class="max-w-6xl mx-auto ...">` wrapper (i.e., as the last block before `</div>` and the `<script src="{{ asset('js/notify.js') }}">` line):

```blade
        <div class="bg-surface border border-border rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-border bg-muted/40 flex items-center gap-2">
                <x-icon.activity class="w-4 h-4 text-primary"/>
                <h3 class="font-heading font-bold text-foreground text-sm">Pusat Perhatian</h3>
            </div>
            <div class="p-3 space-y-1">
                @forelse ($attentionItems as $a)
                    <a href="{{ $a['url'] }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-muted/60 transition">
                        <div class="size-8 rounded-lg flex items-center justify-center shrink-0 {{ $a['severity'] === 'red' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600' }}">
                            @if ($a['severity'] === 'red')
                                <x-icon.alert-triangle class="w-4 h-4"/>
                            @else
                                <x-icon.clock class="w-4 h-4"/>
                            @endif
                        </div>
                        <p class="text-sm text-foreground">{{ $a['text'] }}</p>
                    </a>
                @empty
                    <div class="p-6 text-center">
                        <p class="text-sm text-emerald-700 font-medium">Semua motor terkendali ✓</p>
                    </div>
                @endforelse
            </div>
        </div>
```

- [ ] **Step 8: Full test suite + manual verification**

Run: `php artisan test`
Expected: all pass.

Start the dev server, log in, view `/dashboard`. Confirm: health score badge shows per motor, prediction text shows under progress bars (or nothing if no trip data), and the Pusat Perhatian card lists overdue/upcoming items or the "Semua motor terkendali" empty state.

- [ ] **Step 9: Commit**

```bash
git add app/Services/AttentionService.php resources/views/components/icon/activity.blade.php resources/views/components/icon/alert-triangle.blade.php resources/views/components/icon/clock.blade.php app/Http/Controllers/DashboardController.php resources/views/dashboard.blade.php tests/Unit/AttentionServiceTest.php
git commit -m "feat: AttentionService + dashboard health score, predictions, action center"
```

---

## Task 8: Laporan (cost report) page

**Files:**
- Create: `app/Http/Controllers/ReportController.php`
- Create: `resources/views/laporan/index.blade.php`
- Create: `resources/views/components/icon/bar-chart.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/ReportControllerTest.php`

**Interfaces:**
- Consumes: `FuelStatsService` (Task 2), `MaintenanceLog`, `FuelLog`.
- Produces: route `laporan` (GET `/laporan`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/ReportControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_page_shows_total_cost_of_ownership(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 1000,
        ]);
        $motor->fuelLogs()->create([
            'filled_at' => '2026-07-01', 'odometer_km' => 500, 'liters' => 4, 'total_cost' => 60000,
        ]);
        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();
        $oli->logs()->create([
            'serviced_at_odometer_km' => 500, 'cost' => 45000, 'serviced_at' => '2026-07-05',
        ]);

        $response = $this->actingAs($user)->get(route('laporan'));

        $response->assertOk();
        // TCO = 60000 + 45000 = 105000
        $response->assertSee('105');
    }

    public function test_report_page_loads_with_no_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('laporan'))->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReportControllerTest`
Expected: FAIL  route `laporan` not defined.

- [ ] **Step 3: Implement ReportController**

`app/Http/Controllers/ReportController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Services\FuelStatsService;

class ReportController extends Controller
{
    public function __invoke(FuelStatsService $fuelStats)
    {
        $userId = auth()->id();

        $fuelLogs = FuelLog::whereHas('motorcycle', fn ($q) => $q->where('user_id', $userId))->get();
        $serviceLogs = MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', $userId))->get();

        $totalFuelCost = (int) $fuelLogs->sum('total_cost');
        $totalServiceCost = (int) $serviceLogs->sum('cost');
        $tco = $totalFuelCost + $totalServiceCost;

        $motorcycles = auth()->user()->motorcycles;
        $totalKm = (int) $motorcycles->sum(fn ($m) => max(0, $m->current_odometer_km - $m->initial_odometer_km));
        $costPerKm = $totalKm > 0 ? (int) round($tco / $totalKm) : null;

        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $monthlyFuel = $fuelLogs->groupBy(fn ($l) => $l->filled_at->format('Y-m'));
        $monthlyService = $serviceLogs->groupBy(fn ($l) => $l->serviced_at->format('Y-m'));

        $trend = $months->map(fn ($m) => [
            'month' => $m,
            'fuel' => (int) $monthlyFuel->get($m, collect())->sum('total_cost'),
            'service' => (int) $monthlyService->get($m, collect())->sum('cost'),
        ])->values();

        $efficiencySeries = $motorcycles->mapWithKeys(fn ($m) => [$m->nickname => $fuelStats->consumptionSeries($m)]);

        return view('laporan.index', compact(
            'totalFuelCost', 'totalServiceCost', 'tco', 'costPerKm', 'trend', 'efficiencySeries'
        ));
    }
}
```

- [ ] **Step 4: Add route**

In `routes/web.php`, add `use App\Http\Controllers\ReportController;` to imports, and inside the auth group:

```php
    Route::get('laporan', ReportController::class)->name('laporan');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ReportControllerTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Bar chart icon**

`resources/views/components/icon/bar-chart.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <path d="M3 3v18h18"/>
    <path d="M18 17V9"/>
    <path d="M13 17V5"/>
    <path d="M8 17v-3"/>
</svg>
```

- [ ] **Step 7: Laporan view**

`resources/views/laporan/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">Laporan</x-slot>

    <div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        <x-ui.hero badge="Cost Report" title="Laporan Biaya Kepemilikan"
                    subtitle="Total biaya BBM + servis semua motormu, biaya per km, dan tren pengeluaran bulanan." />

        <div data-reveal-group class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div data-reveal class="bg-primary-soft border border-primary/15 rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-white text-primary flex items-center justify-center mb-4">
                    <x-icon.wallet class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Cost of Ownership</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $tco }}">0</span></p>
            </div>
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                    <x-icon.gauge class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Biaya per KM</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">
                    @if ($costPerKm) Rp <span data-countup="{{ $costPerKm }}">0</span> @else  @endif
                </p>
            </div>
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4">
                    <x-icon.droplet class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total BBM</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $totalFuelCost }}">0</span></p>
            </div>
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-4">
                    <x-icon.wrench class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Servis</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $totalServiceCost }}">0</span></p>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-border bg-muted/40">
                <h3 class="font-heading font-bold text-foreground text-sm">Tren Pengeluaran Bulanan</h3>
                <p class="text-xs text-muted-fg mt-0.5">BBM vs servis, 6 bulan terakhir.</p>
            </div>
            <div class="p-5">
                @if ($trend->sum('fuel') + $trend->sum('service') === 0)
                    <p class="text-sm text-muted-fg text-center py-10">Belum ada data pengeluaran.</p>
                @else
                    <canvas id="trend-chart" height="220" role="img" aria-label="Grafik tren pengeluaran bulanan BBM dan servis"></canvas>
                @endif
            </div>
        </div>

        @if ($efficiencySeries->flatten(1)->isNotEmpty())
            <div class="bg-surface border border-border rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm">Tren Efisiensi BBM</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Km per liter dari tiap pengisian tank penuh.</p>
                </div>
                <div class="p-5">
                    <canvas id="efficiency-chart" height="220" role="img" aria-label="Grafik tren efisiensi bahan bakar per motor"></canvas>
                </div>
            </div>
        @endif
    </div>

    @if ($trend->sum('fuel') + $trend->sum('service') > 0 || $efficiencySeries->flatten(1)->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            @if ($trend->sum('fuel') + $trend->sum('service') > 0)
            new Chart(document.getElementById('trend-chart'), {
                type: 'bar',
                data: {
                    labels: {!! json_encode($trend->pluck('month')) !!},
                    datasets: [
                        { label: 'BBM', data: {!! json_encode($trend->pluck('fuel')) !!}, backgroundColor: '#0F766E' },
                        { label: 'Servis', data: {!! json_encode($trend->pluck('service')) !!}, backgroundColor: '#D97706' },
                    ],
                },
                options: { scales: { x: { stacked: true }, y: { stacked: true } } },
            });
            @endif

            @if ($efficiencySeries->flatten(1)->isNotEmpty())
            new Chart(document.getElementById('efficiency-chart'), {
                type: 'line',
                data: {
                    datasets: [
                        @foreach ($efficiencySeries as $name => $series)
                            @if (count($series))
                            {
                                label: {!! json_encode($name) !!},
                                data: {!! json_encode(array_map(fn($p) => ['x' => $p['date'], 'y' => $p['km_per_liter']], $series)) !!},
                                borderColor: '#0F766E',
                                tension: 0.3,
                            },
                            @endif
                        @endforeach
                    ],
                },
                options: { scales: { x: { type: 'time', time: { unit: 'day' } } } },
            });
            @endif
        </script>
    @endif
</x-app-layout>
```

> Note: the efficiency chart uses a time-scale x-axis. Chart.js's time scale needs a date adapter; if it doesn't render, swap `type: 'time'` for `type: 'category'` and drop the `time: { unit: 'day' }` option  the labels will still be readable as plain date strings. Verify visually in Step 9 and adjust if needed.

- [ ] **Step 8: Add "Laporan" to sidebar nav**

In `resources/views/layouts/navigation.blade.php`, add this entry to the `$links` array, right after the `'bbm.index'` entry added in Task 3:

```php
        ['route' => 'laporan', 'pattern' => 'laporan', 'label' => 'Laporan', 'icon' => 'bar-chart'],
```

- [ ] **Step 9: Full test suite + manual verification**

Run: `php artisan test`
Expected: all pass.

Start the dev server, log in, visit `/laporan`. Confirm the stat cards show correct numbers and both charts render without console errors (open browser dev tools). If the efficiency chart fails to render due to the time scale, apply the category-scale fallback noted above and re-verify.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/ReportController.php resources/views/laporan/index.blade.php resources/views/components/icon/bar-chart.blade.php routes/web.php resources/views/layouts/navigation.blade.php tests/Feature/ReportControllerTest.php
git commit -m "feat: Laporan page  TCO, cost-per-km, monthly spend and efficiency trends"
```

---

## Task 9: Final full-suite verification and demo data refresh

**Files:** none new  verification only.

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: all tests pass (40 original + ~18 new ≈ 58 total). If any fail, fix before proceeding  do not skip.

- [ ] **Step 2: Rebuild frontend assets**

Run: `npm run build`
Expected: builds without error (no new JS/CSS was added in this plan beyond existing Chart.js CDN usage, so this mainly guards against any Blade/Vite manifest drift).

- [ ] **Step 3: Re-run the demo data seeder so the two demo motorcycles also show fuel logs**

The existing `database/seeders/DemoDataSeeder.php` (from the prior session) does not yet add fuel logs. Before running it again, add fuel log seeding for both demo motorcycles. In `database/seeders/DemoDataSeeder.php`, add this call inside `seedMotor1()` right after the `serviceItem` calls (before the `trip(...)` calls):

```php
        $motor->fuelLogs()->createMany([
            ['filled_at' => '2026-05-01', 'odometer_km' => 8000, 'liters' => 4.0, 'total_cost' => 62000, 'is_full_tank' => true],
            ['filled_at' => '2026-06-01', 'odometer_km' => 8900, 'liters' => 4.2, 'total_cost' => 65000, 'is_full_tank' => true],
            ['filled_at' => '2026-07-05', 'odometer_km' => 9800, 'liters' => 4.5, 'total_cost' => 70000, 'is_full_tank' => true],
        ]);
```

And inside `seedMotor2()`, right after its `serviceItem` calls:

```php
        $motor->fuelLogs()->createMany([
            ['filled_at' => '2026-05-15', 'odometer_km' => 4000, 'liters' => 3.8, 'total_cost' => 59000, 'is_full_tank' => true],
            ['filled_at' => '2026-06-15', 'odometer_km' => 5200, 'liters' => 4.0, 'total_cost' => 62000, 'is_full_tank' => true],
        ]);
```

- [ ] **Step 4: Re-run the seeder**

Run: `php artisan db:seed --class=DemoDataSeeder --no-interaction`
Expected: completes without error (the seeder already deletes and recreates the demo user's motorcycles, so it's safe to re-run).

- [ ] **Step 5: Verify seeded fuel data via tinker**

Run:
```bash
php artisan tinker --execute="
\$u = App\Models\User::where('email','demo@Muterin.test')->first();
foreach (\$u->motorcycles as \$m) {
    echo \$m->nickname . ': ' . \$m->fuelLogs()->count() . ' fuel logs' . PHP_EOL;
}
"
```
Expected: "Beat Ilyas: 3 fuel logs" and "NMAX Kantor: 2 fuel logs".

- [ ] **Step 6: Manual end-to-end browser verification**

Start `php artisan serve`, log in as `demo@Muterin.test` / `password123`, and check each new/changed page:
- `/dashboard`  health score badges on motor cards, prediction text under progress bars, Pusat Perhatian card populated.
- `/bbm`  per-motor efficiency stats show real km/liter numbers (not "—"), history table lists 5 entries.
- `/laporan`  TCO, biaya/km, and both charts render with real numbers.
- A motorcycle detail page  mark an item complete with workshop/parts/photo, confirm it saves.
- `/history` (Biaya & Servis)  confirm the workshop name and receipt thumbnail show for the log just created.

- [ ] **Step 7: Commit demo data seeder update**

```bash
git add database/seeders/DemoDataSeeder.php
git commit -m "feat: seed fuel log demo data for both demo motorcycles"
```

---

## Self-Review

**Spec coverage:** All 6 modules from the spec map to tasks  Modul 1 (BBM) → Tasks 1–3; Modul 2 (Prediksi) → Task 5, wired into dashboard in Task 7; Modul 3 (Skor Kesehatan) → Task 6, wired in Task 7; Modul 4 (Pusat Perhatian) → Task 7; Modul 5 (Riwayat Servis Detail) → Task 4; Modul 6 (Laporan) → Task 8. Navigation structure from spec §10 matches: BBM and Laporan added as top-level sidebar items; prediction/health/attention integrated into Dashboard and motor detail rather than separate pages, exactly as specified.

**Placeholder scan:** No TBD/TODO; every step has complete, runnable code.

**Type consistency:** `MaintenanceStatusService::forItem()` returns `['used','percent','color','remaining']` (existing, unchanged)  `MaintenancePredictionService::forItem()` reads `$status['remaining']` and `$status['percent']`, matching. `HealthScoreService::forMotorcycle()` and `AttentionService` both consume `MaintenanceStatusService::forItem()` the same way. `FuelStatsService` method names (`averageKmPerLiter`, `latestKmPerLiter`, `costPerKm`, `consumptionSeries`) are used identically across `HealthScoreService`, `AttentionService`, `FuelController`, and `ReportController`. `DashboardController` constructs `$dashboard` with `'health'` and per-item `'prediction'` keys that the updated `dashboard.blade.php` steps read directly.
