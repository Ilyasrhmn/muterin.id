# Motor Maintenance Tracker (Muterin)  Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Web app Laravel untuk pengendara motor: rekam jarak tempuh via GPS, kelola perawatan multi-motor berbasis km, dan tampilkan status/riwayat + peta perjalanan.

**Architecture:** Laravel (backend + REST-ish JSON endpoint + Blade views). Auth pakai Laravel Breeze (Blade stack). Interaktivitas ringan pakai Alpine.js. GPS tracking & peta murni client-side JS (Leaflet + OpenStreetMap); jarak dihitung di browser (haversine) lalu di-POST ke Laravel. Logika status perawatan dipusatkan di satu Service class.

**Tech Stack:** Laravel 11, PHP 8.2+, MySQL/SQLite, Laravel Breeze (Blade + Alpine.js + Tailwind), Leaflet.js 1.9 (via CDN), PHPUnit.

## Global Constraints

- Laravel 11, PHP 8.2+.
- Frontend: Blade + Alpine.js + Tailwind (bawaan Breeze). TANPA Livewire/Inertia/Vue.
- Peta: Leaflet.js + OpenStreetMap tile. TANPA Google Maps / API key berbayar.
- Jarak GPS dihitung client-side (haversine), server hanya menerima hasil.
- Status perawatan: hijau `<80%`, kuning `80–100%`, merah `>100%` dari `interval_km`. Satu-satunya sumber logika ini adalah `MaintenanceStatusService`.
- 4 item perawatan default per motor: `Oli Mesin` (2500 km), `Ban` (12000 km), `Aki` (15000 km), `Servis Rutin` (4000 km). Interval bisa diedit user. Angka default ini knob kalibrasi  boleh dituning.
- Semua data motor/trip/perawatan milik user login (scope `user_id`); user tidak boleh akses data user lain.
- Commit sering, satu commit per task minimal.

---

## File Structure

```
app/Models/            User, Motorcycle, MaintenanceItem, MaintenanceLog, Trip, MapPin(S), RoutePlan(S)
app/Services/          MaintenanceStatusService
app/Http/Controllers/  MotorcycleController, MaintenanceController, TripController,
                       DashboardController, MapController(S)
database/migrations/   satu file per tabel
database/factories/    MotorcycleFactory, MaintenanceItemFactory, TripFactory
resources/views/       dashboard, motorcycles/*, riding/*, history/*, map/*(S), layouts
routes/web.php         semua route (auth via Breeze)
tests/Feature/         MotorcycleTest, TripTest, MaintenanceTest
tests/Unit/            MaintenanceStatusServiceTest
```
`(S)` = Stretch, dibuat hanya jika waktu tersisa.

---

## Task 1: Project Setup + Auth (Breeze)

**Files:**
- Create: seluruh skeleton Laravel di `D:/Ilyas Nur Rohman/Dicoding/Muterin`
- Modify: `.env` (DB), `routes/web.php`

**Interfaces:**
- Produces: aplikasi Laravel jalan di `php artisan serve`, halaman login/register Breeze, `auth()->user()` tersedia, middleware `auth`.

- [ ] **Step 1: Buat project Laravel di folder Muterin**

```bash
cd "D:/Ilyas Nur Rohman/Dicoding"
composer create-project laravel/laravel Muterin
cd Muterin
```

- [ ] **Step 2: Pakai SQLite biar cepat (tanpa setup MySQL)**

Edit `.env`  hapus baris `DB_*` lain, sisakan:

```
DB_CONNECTION=sqlite
```

```bash
# Windows PowerShell
New-Item -ItemType File database/database.sqlite
php artisan migrate
```

- [ ] **Step 3: Install Breeze (Blade + Alpine + Tailwind)**

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install
npm run build
php artisan migrate
```

- [ ] **Step 4: Jalankan & verifikasi**

Run: `php artisan serve` lalu buka `http://127.0.0.1:8000`
Expected: landing Breeze muncul; `/register` & `/login` berfungsi; setelah login masuk `/dashboard`.

- [ ] **Step 5: Commit**

```bash
git init
git add -A
git commit -m "chore: scaffold Laravel + Breeze auth"
```

---

## Task 2: Model Motorcycle + MaintenanceItem (auto-create 4 item)

**Files:**
- Create: `database/migrations/xxxx_create_motorcycles_table.php`
- Create: `database/migrations/xxxx_create_maintenance_items_table.php`
- Create: `app/Models/Motorcycle.php`, `app/Models/MaintenanceItem.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/MotorcycleTest.php`

**Interfaces:**
- Produces:
  - `Motorcycle` fields: `user_id, nickname, brand, model, year, initial_odometer_km, current_odometer_km, is_active`.
  - `MaintenanceItem` fields: `motorcycle_id, name, interval_km, last_service_odometer_km`.
  - `Motorcycle::booted()` membuat 4 `MaintenanceItem` default otomatis saat create.
  - Relasi: `User hasMany motorcycles`, `Motorcycle hasMany maintenanceItems, trips`, `MaintenanceItem belongsTo motorcycle, hasMany logs`.

- [ ] **Step 1: Migration motorcycles**

`database/migrations/xxxx_create_motorcycles_table.php`:

```php
public function up(): void
{
    Schema::create('motorcycles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('nickname');
        $table->string('brand')->nullable();
        $table->string('model')->nullable();
        $table->unsignedSmallInteger('year')->nullable();
        $table->unsignedInteger('initial_odometer_km')->default(0);
        $table->unsignedInteger('current_odometer_km')->default(0);
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });
}
```

- [ ] **Step 2: Migration maintenance_items**

```php
public function up(): void
{
    Schema::create('maintenance_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('motorcycle_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->unsignedInteger('interval_km');
        $table->unsignedInteger('last_service_odometer_km')->default(0);
        $table->timestamps();
    });
}
```

- [ ] **Step 3: Tulis failing test  buat motor otomatis bikin 4 item**

`tests/Feature/MotorcycleTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Motorcycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorcycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_motorcycle_seeds_four_default_items(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id,
            'nickname' => 'Beat Merah',
            'initial_odometer_km' => 10000,
            'current_odometer_km' => 10000,
        ]);

        $this->assertCount(4, $motor->maintenanceItems);
        $this->assertEqualsCanonicalizing(
            ['Oli Mesin', 'Ban', 'Aki', 'Servis Rutin'],
            $motor->maintenanceItems->pluck('name')->all()
        );
        // last_service dimulai dari odometer awal
        $this->assertEquals(10000, $motor->maintenanceItems->firstWhere('name', 'Oli Mesin')->last_service_odometer_km);
    }
}
```

- [ ] **Step 4: Run  pastikan gagal**

Run: `php artisan test --filter=test_creating_motorcycle_seeds_four_default_items`
Expected: FAIL (class Motorcycle belum ada / relasi kosong).

- [ ] **Step 5: Model MaintenanceItem**

`app/Models/MaintenanceItem.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceItem extends Model
{
    protected $fillable = ['motorcycle_id', 'name', 'interval_km', 'last_service_odometer_km'];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }
}
```

- [ ] **Step 6: Model Motorcycle + auto-seed item**

`app/Models/Motorcycle.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Motorcycle extends Model
{
    protected $fillable = [
        'user_id', 'nickname', 'brand', 'model', 'year',
        'initial_odometer_km', 'current_odometer_km', 'is_active',
    ];

    public const DEFAULT_ITEMS = [
        ['name' => 'Oli Mesin',   'interval_km' => 2500],
        ['name' => 'Ban',         'interval_km' => 12000],
        ['name' => 'Aki',         'interval_km' => 15000],
        ['name' => 'Servis Rutin','interval_km' => 4000],
    ];

    protected static function booted(): void
    {
        static::created(function (Motorcycle $motor) {
            foreach (self::DEFAULT_ITEMS as $item) {
                $motor->maintenanceItems()->create([
                    'name' => $item['name'],
                    'interval_km' => $item['interval_km'],
                    'last_service_odometer_km' => $motor->current_odometer_km,
                ]);
            }
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function maintenanceItems(): HasMany { return $this->hasMany(MaintenanceItem::class); }
    public function trips(): HasMany { return $this->hasMany(Trip::class); }
}
```

- [ ] **Step 7: Tambah relasi di User**

Tambahkan di `app/Models/User.php`:

```php
public function motorcycles(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Motorcycle::class);
}
```

- [ ] **Step 8: Run  pastikan lulus**

Run: `php artisan test --filter=MotorcycleTest`
Expected: PASS.

> Catatan: test ini menyentuh relasi `trips` & model `MaintenanceLog`/`Trip` yang belum ada. Buat stub kosongnya sekarang agar autoload tidak error: `php artisan make:model Trip` dan `php artisan make:model MaintenanceLog` (migration & isi lengkap menyusul di Task 4 & 6). Beri relasi minimal `Motorcycle::trips()` sudah ada; `Trip` stub cukup `protected $fillable = [];` untuk saat ini.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: motorcycle model with auto-seeded maintenance items"
```

---

## Task 3: Motorcycle CRUD + pilih motor aktif

**Files:**
- Create: `app/Http/Controllers/MotorcycleController.php`
- Create: `resources/views/motorcycles/index.blade.php`, `create.blade.php`, `edit.blade.php`, `show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/MotorcycleTest.php` (tambah)

**Interfaces:**
- Consumes: `Motorcycle` model (Task 2).
- Produces: resource routes `motorcycles.*`, route `motorcycles.activate` (POST) yang set satu motor `is_active=true` & sisanya false untuk user itu.

- [ ] **Step 1: Failing test  user hanya lihat motornya sendiri + activate**

Tambah di `MotorcycleTest`:

```php
public function test_user_only_sees_own_motorcycles(): void
{
    $me = User::factory()->create();
    $other = User::factory()->create();
    Motorcycle::create(['user_id' => $me->id, 'nickname' => 'Punyaku']);
    Motorcycle::create(['user_id' => $other->id, 'nickname' => 'Punya Orang']);

    $res = $this->actingAs($me)->get(route('motorcycles.index'));
    $res->assertSee('Punyaku');
    $res->assertDontSee('Punya Orang');
}

public function test_activating_one_motorcycle_deactivates_others(): void
{
    $user = User::factory()->create();
    $a = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A', 'is_active' => true]);
    $b = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'B']);

    $this->actingAs($user)->post(route('motorcycles.activate', $b));

    $this->assertFalse($a->fresh()->is_active);
    $this->assertTrue($b->fresh()->is_active);
}
```

- [ ] **Step 2: Run  pastikan gagal**

Run: `php artisan test --filter=MotorcycleTest`
Expected: FAIL (route belum ada).

- [ ] **Step 3: Routes**

Tambah di `routes/web.php` dalam grup `middleware('auth')`:

```php
use App\Http\Controllers\MotorcycleController;

Route::resource('motorcycles', MotorcycleController::class);
Route::post('motorcycles/{motorcycle}/activate', [MotorcycleController::class, 'activate'])
    ->name('motorcycles.activate');
```

- [ ] **Step 4: Controller**

`app/Http/Controllers/MotorcycleController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Models\Motorcycle;
use Illuminate\Http\Request;

class MotorcycleController extends Controller
{
    public function index()
    {
        $motorcycles = auth()->user()->motorcycles()->with('maintenanceItems')->get();
        return view('motorcycles.index', compact('motorcycles'));
    }

    public function create() { return view('motorcycles.create'); }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['current_odometer_km'] = $data['initial_odometer_km'];
        auth()->user()->motorcycles()->create($data);
        return redirect()->route('motorcycles.index')->with('status', 'Motor ditambahkan.');
    }

    public function show(Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);
        $motorcycle->load('maintenanceItems.logs');
        return view('motorcycles.show', compact('motorcycle'));
    }

    public function edit(Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);
        return view('motorcycles.edit', compact('motorcycle'));
    }

    public function update(Request $request, Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);
        $motorcycle->update($this->validated($request));
        return redirect()->route('motorcycles.show', $motorcycle)->with('status', 'Motor diperbarui.');
    }

    public function destroy(Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);
        $motorcycle->delete();
        return redirect()->route('motorcycles.index')->with('status', 'Motor dihapus.');
    }

    public function activate(Motorcycle $motorcycle)
    {
        $this->authorizeOwner($motorcycle);
        auth()->user()->motorcycles()->update(['is_active' => false]);
        $motorcycle->update(['is_active' => true]);
        return back()->with('status', "Motor aktif: {$motorcycle->nickname}");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nickname' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1980|max:2100',
            'initial_odometer_km' => 'required|integer|min:0',
        ]);
    }

    private function authorizeOwner(Motorcycle $motorcycle): void
    {
        abort_unless($motorcycle->user_id === auth()->id(), 403);
    }
}
```

- [ ] **Step 5: Views (index, create, edit, show)**

`resources/views/motorcycles/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Motor Saya</h2></x-slot>
    <div class="max-w-4xl mx-auto p-4 space-y-4">
        @if (session('status'))<div class="p-3 bg-green-100 rounded">{{ session('status') }}</div>@endif
        <a href="{{ route('motorcycles.create') }}" class="inline-block px-4 py-2 bg-blue-600 text-white rounded">+ Tambah Motor</a>
        <div class="grid gap-3">
            @forelse ($motorcycles as $motor)
                <div class="border rounded p-4 flex justify-between items-center {{ $motor->is_active ? 'ring-2 ring-blue-500' : '' }}">
                    <div>
                        <a href="{{ route('motorcycles.show', $motor) }}" class="font-bold">{{ $motor->nickname }}</a>
                        <p class="text-sm text-gray-500">{{ $motor->brand }} {{ $motor->model }}  {{ number_format($motor->current_odometer_km) }} km</p>
                    </div>
                    @unless ($motor->is_active)
                        <form method="POST" action="{{ route('motorcycles.activate', $motor) }}">@csrf
                            <button class="px-3 py-1 text-sm bg-gray-200 rounded">Jadikan Aktif</button>
                        </form>
                    @else
                        <span class="text-blue-600 text-sm font-medium">Aktif</span>
                    @endunless
                </div>
            @empty
                <p class="text-gray-500">Belum ada motor. Tambahkan satu.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
```

`resources/views/motorcycles/create.blade.php` (form; `edit` sama, ganti action & method PUT + value dari `$motorcycle`):

```blade
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Tambah Motor</h2></x-slot>
    <div class="max-w-lg mx-auto p-4">
        <form method="POST" action="{{ route('motorcycles.store') }}" class="space-y-3">@csrf
            <div><label>Nama/Nickname</label><input name="nickname" value="{{ old('nickname') }}" class="w-full border rounded p-2" required></div>
            <div><label>Merk</label><input name="brand" value="{{ old('brand') }}" class="w-full border rounded p-2"></div>
            <div><label>Tipe</label><input name="model" value="{{ old('model') }}" class="w-full border rounded p-2"></div>
            <div><label>Tahun</label><input type="number" name="year" value="{{ old('year') }}" class="w-full border rounded p-2"></div>
            <div><label>Odometer saat ini (km)</label><input type="number" name="initial_odometer_km" value="{{ old('initial_odometer_km', 0) }}" class="w-full border rounded p-2" required></div>
            @error('nickname')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
            <button class="px-4 py-2 bg-blue-600 text-white rounded">Simpan</button>
        </form>
    </div>
</x-app-layout>
```

`resources/views/motorcycles/edit.blade.php`: salin form di atas, ubah `<form method="POST" action="{{ route('motorcycles.update', $motorcycle) }}">@csrf @method('PUT')` dan setiap `old('x')` jadi `old('x', $motorcycle->x)`.

`resources/views/motorcycles/show.blade.php`: tampilkan detail + status item (dipakai penuh di Task 5; untuk sekarang cukup tampilkan nama, odometer, dan daftar `maintenanceItems` beserta `interval_km`).

- [ ] **Step 6: Run  pastikan lulus**

Run: `php artisan test --filter=MotorcycleTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: motorcycle CRUD and active-motorcycle selection"
```

---

## Task 4: MaintenanceStatusService (logika status  TDD inti)

**Files:**
- Create: `app/Services/MaintenanceStatusService.php`
- Test: `tests/Unit/MaintenanceStatusServiceTest.php`

**Interfaces:**
- Consumes: `MaintenanceItem` (punya `interval_km`, `last_service_odometer_km`) + `current_odometer_km` motor.
- Produces:
  - `MaintenanceStatusService::percent(int $used, int $interval): float`
  - `MaintenanceStatusService::color(float $percent): string` → `'green'|'yellow'|'red'`
  - `MaintenanceStatusService::forItem(MaintenanceItem $item, int $currentOdometer): array` → `['used'=>int,'percent'=>float,'color'=>string,'remaining'=>int]`

- [ ] **Step 1: Failing test  batas warna 79/80/100/101**

`tests/Unit/MaintenanceStatusServiceTest.php`:

```php
<?php
namespace Tests\Unit;

use App\Services\MaintenanceStatusService;
use PHPUnit\Framework\TestCase;

class MaintenanceStatusServiceTest extends TestCase
{
    private MaintenanceStatusService $svc;
    protected function setUp(): void { $this->svc = new MaintenanceStatusService(); }

    public function test_percent_calculation(): void
    {
        $this->assertEquals(50.0, $this->svc->percent(1250, 2500));
    }

    public function test_color_boundaries(): void
    {
        $this->assertEquals('green',  $this->svc->color(79.9));
        $this->assertEquals('yellow', $this->svc->color(80.0));
        $this->assertEquals('yellow', $this->svc->color(100.0));
        $this->assertEquals('red',    $this->svc->color(100.1));
    }

    public function test_zero_interval_is_safe_not_divide_by_zero(): void
    {
        // interval 0 tidak valid; jangan crash  anggap 0%
        $this->assertEquals(0.0, $this->svc->percent(500, 0));
    }
}
```

- [ ] **Step 2: Run  pastikan gagal**

Run: `php artisan test --filter=MaintenanceStatusServiceTest`
Expected: FAIL (class belum ada).

- [ ] **Step 3: Implement service**

`app/Services/MaintenanceStatusService.php`:

```php
<?php
namespace App\Services;

use App\Models\MaintenanceItem;

class MaintenanceStatusService
{
    public function percent(int $used, int $interval): float
    {
        if ($interval <= 0) return 0.0;               // ponytail: guard bagi nol
        return round($used / $interval * 100, 1);
    }

    public function color(float $percent): string
    {
        if ($percent < 80) return 'green';
        if ($percent <= 100) return 'yellow';
        return 'red';
    }

    public function forItem(MaintenanceItem $item, int $currentOdometer): array
    {
        $used = max(0, $currentOdometer - $item->last_service_odometer_km);
        $percent = $this->percent($used, $item->interval_km);
        return [
            'used' => $used,
            'percent' => $percent,
            'color' => $this->color($percent),
            'remaining' => max(0, $item->interval_km - $used),
        ];
    }
}
```

- [ ] **Step 4: Run  pastikan lulus**

Run: `php artisan test --filter=MaintenanceStatusServiceTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: maintenance status service with color thresholds"
```

---

## Task 5: Dashboard + status di detail motor

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php`, `resources/views/dashboard.blade.php`, `resources/views/motorcycles/show.blade.php`
- Create: `resources/views/components/status-bar.blade.php`

**Interfaces:**
- Consumes: `MaintenanceStatusService::forItem()` (Task 4), `Motorcycle` (Task 2).
- Produces: route `dashboard` menampilkan tiap motor + progress bar tiap item + badge kuning/merah (in-app alert).

- [ ] **Step 1: Komponen status bar**

`resources/views/components/status-bar.blade.php`:

```blade
@props(['item', 'status'])
@php $bg = ['green'=>'bg-green-500','yellow'=>'bg-yellow-500','red'=>'bg-red-500'][$status['color']]; @endphp
<div class="space-y-1">
    <div class="flex justify-between text-sm">
        <span>{{ $item->name }}</span>
        <span>{{ $status['used'] }} / {{ $item->interval_km }} km ({{ $status['percent'] }}%)</span>
    </div>
    <div class="w-full bg-gray-200 rounded h-2">
        <div class="{{ $bg }} h-2 rounded" style="width: {{ min(100, $status['percent']) }}%"></div>
    </div>
</div>
```

- [ ] **Step 2: DashboardController**

```php
<?php
namespace App\Http\Controllers;

use App\Services\MaintenanceStatusService;

class DashboardController extends Controller
{
    public function __invoke(MaintenanceStatusService $status)
    {
        $motorcycles = auth()->user()->motorcycles()->with('maintenanceItems')->get();
        $data = $motorcycles->map(function ($motor) use ($status) {
            return [
                'motor' => $motor,
                'items' => $motor->maintenanceItems->map(fn ($item) => [
                    'item' => $item,
                    'status' => $status->forItem($item, $motor->current_odometer_km),
                ]),
            ];
        });
        return view('dashboard', ['dashboard' => $data]);
    }
}
```

- [ ] **Step 3: Route dashboard**

Ganti route dashboard bawaan Breeze di `routes/web.php`:

```php
use App\Http\Controllers\DashboardController;
Route::get('/dashboard', DashboardController::class)->middleware(['auth','verified'])->name('dashboard');
```

- [ ] **Step 4: View dashboard**

`resources/views/dashboard.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Dashboard</h2></x-slot>
    <div class="max-w-4xl mx-auto p-4 space-y-6">
        @forelse ($dashboard as $row)
            @php $needsAttention = $row['items']->contains(fn($i) => $i['status']['color'] !== 'green'); @endphp
            <div class="border rounded p-4 space-y-3">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold">{{ $row['motor']->nickname }}</h3>
                    @if ($needsAttention)<span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded">Perlu perhatian</span>@endif
                </div>
                @foreach ($row['items'] as $i)
                    <x-status-bar :item="$i['item']" :status="$i['status']" />
                @endforeach
                <a href="{{ route('motorcycles.show', $row['motor']) }}" class="text-blue-600 text-sm">Detail & tandai servis →</a>
            </div>
        @empty
            <p>Belum ada motor. <a href="{{ route('motorcycles.create') }}" class="text-blue-600">Tambah motor</a>.</p>
        @endforelse
    </div>
</x-app-layout>
```

- [ ] **Step 5: Verifikasi manual**

Run: `php artisan serve` → login → tambah motor → buka `/dashboard`.
Expected: 4 progress bar hijau muncul (odometer awal = last_service, jadi 0%).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: dashboard with maintenance status bars"
```

---

## Task 6: Tandai perawatan selesai + biaya + log

**Files:**
- Create: `database/migrations/xxxx_create_maintenance_logs_table.php`
- Create/replace: `app/Models/MaintenanceLog.php` (isi stub Task 2)
- Create: `app/Http/Controllers/MaintenanceController.php`
- Modify: `routes/web.php`, `resources/views/motorcycles/show.blade.php`
- Test: `tests/Feature/MaintenanceTest.php`

**Interfaces:**
- Consumes: `MaintenanceItem` (Task 2), `Motorcycle.current_odometer_km`.
- Produces: route `maintenance.complete` (POST `maintenance_items/{item}/complete`) → buat `MaintenanceLog` & set `last_service_odometer_km = current_odometer_km`.

- [ ] **Step 1: Migration maintenance_logs**

```php
public function up(): void
{
    Schema::create('maintenance_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('maintenance_item_id')->constrained()->cascadeOnDelete();
        $table->unsignedInteger('serviced_at_odometer_km');
        $table->unsignedInteger('cost')->nullable();
        $table->date('serviced_at');
        $table->string('note')->nullable();
        $table->timestamps();
    });
}
```

- [ ] **Step 2: Model MaintenanceLog**

`app/Models/MaintenanceLog.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    protected $fillable = ['maintenance_item_id', 'serviced_at_odometer_km', 'cost', 'serviced_at', 'note'];
    protected $casts = ['serviced_at' => 'date'];

    public function item(): BelongsTo { return $this->belongsTo(MaintenanceItem::class, 'maintenance_item_id'); }
}
```

- [ ] **Step 3: Failing test  complete bikin log & reset checkpoint**

`tests/Feature/MaintenanceTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Motorcycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_item_creates_log_and_resets_checkpoint(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'initial_odometer_km' => 0, 'current_odometer_km' => 3000,
        ]);
        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();

        $this->actingAs($user)->post(route('maintenance.complete', $oli), [
            'cost' => 45000, 'serviced_at' => '2026-07-19',
        ])->assertRedirect();

        $this->assertEquals(3000, $oli->fresh()->last_service_odometer_km);
        $this->assertDatabaseHas('maintenance_logs', [
            'maintenance_item_id' => $oli->id, 'serviced_at_odometer_km' => 3000, 'cost' => 45000,
        ]);
    }
}
```

- [ ] **Step 4: Run  pastikan gagal**

Run: `php artisan test --filter=MaintenanceTest`
Expected: FAIL (route belum ada).

- [ ] **Step 5: Controller + route**

`app/Http/Controllers/MaintenanceController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Models\MaintenanceItem;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function complete(Request $request, MaintenanceItem $item)
    {
        abort_unless($item->motorcycle->user_id === auth()->id(), 403);

        $data = $request->validate([
            'cost' => 'nullable|integer|min:0',
            'serviced_at' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $odometer = $item->motorcycle->current_odometer_km;
        $item->logs()->create([
            'serviced_at_odometer_km' => $odometer,
            'cost' => $data['cost'] ?? null,
            'serviced_at' => $data['serviced_at'],
            'note' => $data['note'] ?? null,
        ]);
        $item->update(['last_service_odometer_km' => $odometer]);

        return back()->with('status', "{$item->name} ditandai selesai di {$odometer} km.");
    }
}
```

Route (`routes/web.php`, grup auth):

```php
use App\Http\Controllers\MaintenanceController;
Route::post('maintenance_items/{item}/complete', [MaintenanceController::class, 'complete'])
    ->name('maintenance.complete');
```

- [ ] **Step 6: UI di show.blade.php  tombol + form biaya**

Di `resources/views/motorcycles/show.blade.php`, untuk tiap item tambahkan (pakai Alpine untuk toggle form):

```blade
<div x-data="{ open: false }" class="border-t pt-2 mt-2">
    <button @click="open = !open" class="text-sm text-blue-600">Tandai "{{ $item->name }}" selesai</button>
    <form x-show="open" method="POST" action="{{ route('maintenance.complete', $item) }}" class="mt-2 space-y-2">@csrf
        <input type="number" name="cost" placeholder="Biaya (opsional)" class="border rounded p-1 w-full">
        <input type="date" name="serviced_at" value="{{ now()->toDateString() }}" class="border rounded p-1 w-full" required>
        <button class="px-3 py-1 bg-green-600 text-white rounded text-sm">Simpan</button>
    </form>
</div>
```

- [ ] **Step 7: Run  pastikan lulus**

Run: `php artisan test --filter=MaintenanceTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: mark maintenance complete with cost logging"
```

---

## Task 7: Trip recording  backend (odometer bertambah)

**Files:**
- Create: `database/migrations/xxxx_create_trips_table.php`
- Create/replace: `app/Models/Trip.php` (isi stub Task 2)
- Create: `app/Http/Controllers/TripController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TripTest.php`

**Interfaces:**
- Consumes: `Motorcycle` (Task 2).
- Produces:
  - route `trips.store` (POST JSON `{motorcycle_id, distance_km, duration_seconds, path}`) → buat `Trip`, tambah `distance_km` ke `current_odometer_km`.
  - `Trip` fields: `motorcycle_id, distance_km(decimal), duration_seconds, path_json, started_at, ended_at`.

- [ ] **Step 1: Migration trips**

```php
public function up(): void
{
    Schema::create('trips', function (Blueprint $table) {
        $table->id();
        $table->foreignId('motorcycle_id')->constrained()->cascadeOnDelete();
        $table->decimal('distance_km', 8, 2);
        $table->unsignedInteger('duration_seconds')->default(0);
        $table->json('path_json')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('ended_at')->nullable();
        $table->timestamps();
    });
}
```

- [ ] **Step 2: Model Trip**

`app/Models/Trip.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trip extends Model
{
    protected $fillable = ['motorcycle_id', 'distance_km', 'duration_seconds', 'path_json', 'started_at', 'ended_at'];
    protected $casts = ['path_json' => 'array', 'started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function motorcycle(): BelongsTo { return $this->belongsTo(Motorcycle::class); }
}
```

- [ ] **Step 3: Failing test  trip nambah odometer**

`tests/Feature/TripTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Motorcycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    public function test_finishing_trip_increments_odometer(): void
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
            'path' => [[-6.2, 106.8], [-6.21, 106.81]],
        ])->assertCreated();

        $this->assertEquals(1012, (int) round($motor->fresh()->current_odometer_km));
        $this->assertDatabaseHas('trips', ['motorcycle_id' => $motor->id, 'distance_km' => 12.5]);
    }

    public function test_cannot_add_trip_to_other_users_motorcycle(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $other->id, 'nickname' => 'X']);

        $this->actingAs($user)->postJson(route('trips.store'), [
            'motorcycle_id' => $motor->id, 'distance_km' => 5, 'duration_seconds' => 60,
        ])->assertForbidden();
    }
}
```

- [ ] **Step 4: Run  pastikan gagal**

Run: `php artisan test --filter=TripTest`
Expected: FAIL.

- [ ] **Step 5: Controller + route**

`app/Http/Controllers/TripController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Models\Trip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function create()
    {
        $motorcycles = auth()->user()->motorcycles()->get();
        return view('riding.index', compact('motorcycles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'motorcycle_id' => 'required|exists:motorcycles,id',
            'distance_km' => 'required|numeric|min:0',
            'duration_seconds' => 'required|integer|min:0',
            'path' => 'nullable|array',
        ]);

        $motor = Motorcycle::findOrFail($data['motorcycle_id']);
        abort_unless($motor->user_id === auth()->id(), 403);

        $trip = $motor->trips()->create([
            'distance_km' => $data['distance_km'],
            'duration_seconds' => $data['duration_seconds'],
            'path_json' => $data['path'] ?? null,
            'started_at' => now()->subSeconds($data['duration_seconds']),
            'ended_at' => now(),
        ]);
        $motor->increment('current_odometer_km', (int) round($data['distance_km']));

        return response()->json(['ok' => true, 'trip_id' => $trip->id], 201);
    }
}
```

Route:

```php
use App\Http\Controllers\TripController;
Route::get('riding', [TripController::class, 'create'])->name('riding');
Route::post('trips', [TripController::class, 'store'])->name('trips.store');
```

> Catatan desain: odometer disimpan integer, `distance_km` disimpan desimal penuh di `trips`. Pembulatan hanya di odometer  akumulasi presisi ada di riwayat trip. (ponytail: cukup untuk MVP; kalau butuh presisi odometer, ubah kolom ke decimal nanti.)

- [ ] **Step 6: Run  pastikan lulus**

Run: `php artisan test --filter=TripTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: trip store endpoint increments odometer"
```

---

## Task 8: Trip recording  frontend GPS (Leaflet + haversine + Alpine)

**Files:**
- Create: `resources/views/riding/index.blade.php`
- Create: `public/js/trip-recorder.js`

**Interfaces:**
- Consumes: route `trips.store` (Task 7), `motorcycles` (Task 7 `create`).
- Produces: UI Mulai/Selesai; JS `watchPosition` → haversine → POST hasil.

- [ ] **Step 1: Halaman riding**

`resources/views/riding/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Riding</h2></x-slot>
    <div class="max-w-lg mx-auto p-4 space-y-4" id="riding-app">
        <label class="block">Pilih motor
            <select id="motor-select" class="w-full border rounded p-2">
                @foreach ($motorcycles as $m)
                    <option value="{{ $m->id }}" @selected($m->is_active)>{{ $m->nickname }} ({{ number_format($m->current_odometer_km) }} km)</option>
                @endforeach
            </select>
        </label>
        <div class="text-center py-6 border rounded">
            <p class="text-4xl font-bold"><span id="distance">0.00</span> km</p>
            <p class="text-gray-500"><span id="duration">00:00</span></p>
        </div>
        <button id="start-btn" class="w-full py-3 bg-green-600 text-white rounded text-lg">Mulai Perjalanan</button>
        <button id="stop-btn" class="w-full py-3 bg-red-600 text-white rounded text-lg hidden">Selesai Perjalanan</button>
        <p id="gps-msg" class="text-sm text-red-600"></p>
    </div>
    @csrf
    <script src="{{ asset('js/trip-recorder.js') }}"></script>
</x-app-layout>
```

- [ ] **Step 2: Logika recorder**

`public/js/trip-recorder.js`:

```js
(function () {
  const $ = (id) => document.getElementById(id);
  const startBtn = $('start-btn'), stopBtn = $('stop-btn');
  const IDLE_MS = 5 * 60 * 1000;        // ponytail: idle auto-stop 5 menit, tuning di device asli
  const MAX_JUMP_KM = 1;                // ponytail: buang lonjakan >1km antar update (outlier GPS)

  let watchId = null, last = null, distance = 0, startTs = 0, path = [], idleTimer = null, tick = null;

  function haversine(a, b) {
    const R = 6371, toRad = (d) => d * Math.PI / 180;
    const dLat = toRad(b[0] - a[0]), dLng = toRad(b[1] - a[1]);
    const s = Math.sin(dLat/2)**2 + Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(s), Math.sqrt(1-s));
  }

  function fmtDur(sec) {
    const m = String(Math.floor(sec/60)).padStart(2,'0'), s = String(sec%60).padStart(2,'0');
    return `${m}:${s}`;
  }

  function onPos(pos) {
    const p = [pos.coords.latitude, pos.coords.longitude];
    if (last) {
      const d = haversine(last, p);
      if (d <= MAX_JUMP_KM) { distance += d; $('distance').textContent = distance.toFixed(2); }
    }
    last = p; path.push(p);
    resetIdle();
  }

  function resetIdle() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(stop, IDLE_MS);
  }

  function start() {
    if (!navigator.geolocation) { $('gps-msg').textContent = 'Browser tidak mendukung GPS.'; return; }
    navigator.geolocation.getCurrentPosition(() => {
      distance = 0; last = null; path = []; startTs = Date.now();
      $('distance').textContent = '0.00';
      watchId = navigator.geolocation.watchPosition(onPos, onErr, { enableHighAccuracy: true, maximumAge: 0 });
      tick = setInterval(() => $('duration').textContent = fmtDur(Math.floor((Date.now()-startTs)/1000)), 1000);
      startBtn.classList.add('hidden'); stopBtn.classList.remove('hidden');
      resetIdle();
    }, onErr, { enableHighAccuracy: true });
  }

  function onErr(e) { $('gps-msg').textContent = 'Izin GPS ditolak atau tidak tersedia.'; }

  async function stop() {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    clearInterval(tick); clearTimeout(idleTimer); watchId = null;
    const duration = Math.floor((Date.now()-startTs)/1000);
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

- [ ] **Step 3: Link di navigasi**

Tambah link `route('riding')` dan `route('motorcycles.index')` di `resources/views/layouts/navigation.blade.php` (ikuti pola `<x-nav-link>` yang sudah ada dari Breeze).

- [ ] **Step 4: Verifikasi manual (device asli / DevTools sensors)**

Run: `php artisan serve`, buka di HP (atau Chrome DevTools → Sensors → set Location). Mulai → gerakkan lokasi → Selesai.
Expected: jarak bertambah, redirect ke dashboard, odometer motor naik.

> Catatan: `getCurrentPosition`/`watchPosition` butuh HTTPS atau `localhost`. Untuk tes di HP via LAN gunakan tunneling (mis. `php artisan serve` + ngrok) atau uji lewat DevTools sensors di localhost.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: GPS trip recorder frontend with haversine distance"
```

---

## Task 9: Riwayat trip & perawatan

**Files:**
- Create: `app/Http/Controllers/HistoryController.php`
- Create: `resources/views/history/index.blade.php`
- Modify: `routes/web.php`, navigation

**Interfaces:**
- Consumes: `Trip` (Task 7), `MaintenanceLog` (Task 6).
- Produces: route `history` menampilkan daftar trip + daftar log perawatan (dengan biaya & total).

- [ ] **Step 1: Controller**

```php
<?php
namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\MaintenanceLog;

class HistoryController extends Controller
{
    public function __invoke()
    {
        $userId = auth()->id();
        $trips = Trip::whereHas('motorcycle', fn($q) => $q->where('user_id', $userId))
            ->with('motorcycle')->latest('ended_at')->get();
        $logs = MaintenanceLog::whereHas('item.motorcycle', fn($q) => $q->where('user_id', $userId))
            ->with('item.motorcycle')->latest('serviced_at')->get();
        $totalCost = $logs->sum('cost');
        return view('history.index', compact('trips', 'logs', 'totalCost'));
    }
}
```

> Perlu relasi `MaintenanceItem::motorcycle()` (sudah ada, Task 2) dan `MaintenanceLog::item()` (Task 6). Konfirmasi keduanya ada sebelum lanjut.

- [ ] **Step 2: Route**

```php
use App\Http\Controllers\HistoryController;
Route::get('history', HistoryController::class)->name('history');
```

- [ ] **Step 3: View**

`resources/views/history/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Riwayat</h2></x-slot>
    <div class="max-w-4xl mx-auto p-4 grid md:grid-cols-2 gap-6">
        <div>
            <h3 class="font-bold mb-2">Perjalanan</h3>
            @forelse ($trips as $t)
                <div class="border rounded p-3 mb-2 text-sm">
                    <div class="font-medium">{{ $t->motorcycle->nickname }}  {{ $t->distance_km }} km</div>
                    <div class="text-gray-500">{{ $t->ended_at?->format('d M Y H:i') }} · {{ gmdate('H:i:s', $t->duration_seconds) }}</div>
                </div>
            @empty <p class="text-gray-500">Belum ada perjalanan.</p> @endforelse
        </div>
        <div>
            <h3 class="font-bold mb-2">Perawatan <span class="text-sm text-gray-500">(total Rp{{ number_format($totalCost) }})</span></h3>
            @forelse ($logs as $l)
                <div class="border rounded p-3 mb-2 text-sm">
                    <div class="font-medium">{{ $l->item->name }}  {{ $l->item->motorcycle->nickname }}</div>
                    <div class="text-gray-500">{{ $l->serviced_at->format('d M Y') }} · {{ number_format($l->serviced_at_odometer_km) }} km · Rp{{ number_format($l->cost) }}</div>
                </div>
            @empty <p class="text-gray-500">Belum ada perawatan.</p> @endforelse
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 4: Verifikasi manual + commit**

Run: buka `/history` setelah ada trip & servis.
Expected: kedua kolom terisi, total biaya benar.

```bash
git add -A
git commit -m "feat: trip and maintenance history page"
```

---

## Task 10 (STRETCH): Peta Perjalanan  riwayat rute + pin + planner

> Kerjakan HANYA jika Task 1–9 selesai & stabil. Satu peta Leaflet, tiga fungsi.

**Files:**
- Create: `database/migrations/xxxx_create_map_pins_table.php`, `xxxx_create_route_plans_table.php`
- Create: `app/Models/MapPin.php`, `app/Models/RoutePlan.php`
- Create: `app/Http/Controllers/MapController.php`
- Create: `resources/views/map/index.blade.php`, `public/js/map.js`
- Modify: `routes/web.php`

**Interfaces:**
- Produces:
  - `MapPin`: `user_id, category(enum moment|hazard|quiet), lat, lng, title, note`.
  - `RoutePlan`: `user_id, name, points_json`.
  - JSON routes: `GET map/data` (pins + trips path + plans), `POST map/pins`, `DELETE map/pins/{pin}`, `POST map/plans`.

- [ ] **Step 1: Migrations**

```php
// map_pins
Schema::create('map_pins', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->enum('category', ['moment', 'hazard', 'quiet']);
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
    $table->string('title');
    $table->string('note')->nullable();
    $table->timestamps();
});
// route_plans
Schema::create('route_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->json('points_json');
    $table->timestamps();
});
```

- [ ] **Step 2: Models**

```php
// app/Models/MapPin.php
class MapPin extends Model {
    protected $fillable = ['user_id','category','lat','lng','title','note'];
}
// app/Models/RoutePlan.php
class RoutePlan extends Model {
    protected $fillable = ['user_id','name','points_json'];
    protected $casts = ['points_json' => 'array'];
}
```

- [ ] **Step 3: Failing test  simpan pin milik user**

`tests/Feature/MapTest.php`:

```php
public function test_user_can_store_and_list_own_pin(): void
{
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user)->postJson('/map/pins', [
        'category' => 'hazard', 'lat' => -6.2, 'lng' => 106.8, 'title' => 'Jalan rusak',
    ])->assertCreated();

    $this->getJson('/map/data')->assertJsonFragment(['title' => 'Jalan rusak']);
}
```

- [ ] **Step 4: Run  gagal, lalu Controller + routes**

`app/Http/Controllers/MapController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Models\MapPin;
use App\Models\RoutePlan;
use App\Models\Trip;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index() { return view('map.index'); }

    public function data()
    {
        $userId = auth()->id();
        return response()->json([
            'pins' => MapPin::where('user_id', $userId)->get(),
            'plans' => RoutePlan::where('user_id', $userId)->get(),
            'trips' => Trip::whereHas('motorcycle', fn($q) => $q->where('user_id', $userId))
                ->whereNotNull('path_json')->get(['id', 'path_json']),
        ]);
    }

    public function storePin(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|in:moment,hazard,quiet',
            'lat' => 'required|numeric', 'lng' => 'required|numeric',
            'title' => 'required|string|max:255', 'note' => 'nullable|string|max:255',
        ]);
        $pin = auth()->user()->mapPins()->create($data);
        return response()->json($pin, 201);
    }

    public function destroyPin(MapPin $pin)
    {
        abort_unless($pin->user_id === auth()->id(), 403);
        $pin->delete();
        return response()->json(['ok' => true]);
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'points' => 'required|array|min:2',
        ]);
        $plan = auth()->user()->routePlans()->create([
            'name' => $data['name'], 'points_json' => $data['points'],
        ]);
        return response()->json($plan, 201);
    }
}
```

Tambah relasi di `User`: `mapPins()` hasMany MapPin, `routePlans()` hasMany RoutePlan.

Routes:

```php
use App\Http\Controllers\MapController;
Route::get('map', [MapController::class, 'index'])->name('map');
Route::get('map/data', [MapController::class, 'data'])->name('map.data');
Route::post('map/pins', [MapController::class, 'storePin'])->name('map.pins.store');
Route::delete('map/pins/{pin}', [MapController::class, 'destroyPin'])->name('map.pins.destroy');
Route::post('map/plans', [MapController::class, 'storePlan'])->name('map.plans.store');
```

- [ ] **Step 5: View peta (Leaflet via CDN)**

`resources/views/map/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Peta Perjalanan</h2></x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <div class="p-4 space-y-2">
        <div class="flex gap-2 text-sm">
            <label>Mode:
                <select id="mode" class="border rounded p-1">
                    <option value="view">Lihat</option>
                    <option value="moment">+ Momen</option>
                    <option value="hazard">+ Jalan Rawan</option>
                    <option value="quiet">+ Jalan Sepi</option>
                    <option value="plan">+ Titik Rencana</option>
                </select>
            </label>
            <button id="save-plan" class="px-2 py-1 bg-blue-600 text-white rounded">Simpan Rencana</button>
        </div>
        <div id="map" style="height: 70vh" class="rounded border"></div>
    </div>
    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}"></script>
</x-app-layout>
```

- [ ] **Step 6: `public/js/map.js`**

```js
(function () {
  const token = document.querySelector('input[name="_token"]').value;
  const map = L.map('map').setView([-6.2, 106.8], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

  const colors = { moment: 'blue', hazard: 'red', quiet: 'green' };
  let planPoints = [], planLine = null;

  function loadData() {
    fetch('/map/data', { headers: { 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => {
        d.pins.forEach(p => L.circleMarker([p.lat, p.lng], { color: colors[p.category] })
          .bindPopup(`<b>${p.title}</b><br>${p.category}<br>${p.note ?? ''}<br>
            <a href="#" onclick="deletePin(${p.id});return false;">hapus</a>`).addTo(map));
        d.trips.forEach(t => t.path_json && L.polyline(t.path_json, { color: 'purple', weight: 3 }).addTo(map));
      });
  }

  window.deletePin = (id) => fetch(`/map/pins/${id}`, {
    method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
  }).then(() => location.reload());

  map.on('click', (e) => {
    const mode = document.getElementById('mode').value;
    if (mode === 'view') return;
    if (mode === 'plan') {
      planPoints.push([e.latlng.lat, e.latlng.lng]);
      if (planLine) planLine.setLatLngs(planPoints); else planLine = L.polyline(planPoints, { color: 'orange' }).addTo(map);
      return;
    }
    const title = prompt('Judul pin?'); if (!title) return;
    const note = prompt('Catatan (opsional)?') || null;
    fetch('/map/pins', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: JSON.stringify({ category: mode, lat: e.latlng.lat, lng: e.latlng.lng, title, note }),
    }).then(() => location.reload());
  });

  document.getElementById('save-plan').addEventListener('click', () => {
    if (planPoints.length < 2) { alert('Klik minimal 2 titik dulu.'); return; }
    const name = prompt('Nama rencana rute?'); if (!name) return;
    fetch('/map/plans', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: JSON.stringify({ name, points: planPoints }),
    }).then(() => { alert('Rencana disimpan.'); });
  });

  loadData();
})();
```

- [ ] **Step 7: Run test + verifikasi manual + commit**

Run: `php artisan test --filter=MapTest` → PASS. Lalu buka `/map`, klik mode + peta.
Expected: pin muncul dengan warna kategori, jalur trip ungu tampil, rencana bisa disimpan.

```bash
git add -A
git commit -m "feat: trip map with pins, route history and simple planner"
```

---

## Task 11 (STRETCH): Push notification saat transisi kuning/merah

**Files:**
- Create: `public/sw.js` (service worker)
- Modify: dashboard view (subscribe + cek transisi)

**Approach ringkas (tanpa server push/VAPID  pakai Notification API lokal saat app dibuka):**
- Saat dashboard load, JS bandingkan status warna tiap item dengan yang tersimpan di `localStorage`. Jika ada item berubah `green→yellow` atau `→red`, tampilkan `new Notification(...)` (minta izin dulu). Simpan snapshot warna baru.
- Ini "notifikasi saat kondisi tertentu" yang jujur untuk MVP tanpa infra push server.

- [ ] **Step 1: Tambah data status ke dashboard (atribut data)**

Di `dashboard.blade.php`, tiap item beri `data-item-id` & `data-color`.

- [ ] **Step 2: `public/js/notify.js`**

```js
(function () {
  if (!('Notification' in window)) return;
  if (Notification.permission === 'default') Notification.requestPermission();

  const prev = JSON.parse(localStorage.getItem('mtn_colors') || '{}');
  const now = {};
  document.querySelectorAll('[data-item-id]').forEach(el => {
    const id = el.dataset.itemId, color = el.dataset.color;
    now[id] = color;
    const before = prev[id];
    const worsened = (before === 'green' && color !== 'green') || (before !== 'red' && color === 'red');
    if (worsened && Notification.permission === 'granted') {
      new Notification('Waktunya cek perawatan', { body: `${el.dataset.itemName} kini ${color === 'red' ? 'LEWAT batas' : 'mendekati batas'}.` });
    }
  });
  localStorage.setItem('mtn_colors', JSON.stringify(now));
})();
```

- [ ] **Step 3: Include script di dashboard + commit**

```bash
git add -A
git commit -m "feat: local notifications on maintenance status change"
```

> ponytail: skip VAPID/web-push server. Tambah push server-side hanya jika butuh notif saat app tertutup  jelaskan batasan ini di pitch.

---

## Task 12 (STRETCH): Cari bengkel + export PDF

- **Cari bengkel:** di dashboard/show, saat `color==='red'`, tampilkan link:
  `https://www.google.com/maps/search/bengkel+motor+terdekat/` (buka tab baru). Satu baris Blade, tanpa backend.
- **Export PDF riwayat servis:** `composer require barryvdh/laravel-dompdf`, route `history/export` render view sederhana `logs` → `Pdf::loadView(...)->download()`. Satu controller method + satu view print.

- [ ] Commit: `feat: nearest workshop link and service history PDF export`

---

## Self-Review Notes (sudah dicek terhadap spec)

- **Coverage:** Auth(T1), motor CRUD + aktif(T3), item default(T2), status(T4), dashboard(T5), tandai selesai+biaya(T6), trip+odometer(T7/T8), riwayat(T9), peta/pin/planner(T10), push(T11), bengkel+PDF(T12). Item berbasis tanggal sengaja tidak ada (dicoret di spec). Semua requirement Core tercakup.
- **Type consistency:** `MaintenanceStatusService::forItem()` mengembalikan key `used/percent/color/remaining`  dipakai konsisten di `status-bar.blade.php` & dashboard. `color()` mengembalikan `green|yellow|red` dipakai konsisten di UI & notify.js. `trips.store` menerima `{motorcycle_id, distance_km, duration_seconds, path}`  cocok dengan yang dikirim `trip-recorder.js`.
- **Auto-stop & outlier:** knob (`IDLE_MS`, `MAX_JUMP_KM`) ditandai untuk tuning device asli.
```
