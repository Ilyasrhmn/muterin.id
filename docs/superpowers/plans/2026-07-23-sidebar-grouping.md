# Sidebar Grup Lipat (Nutrio-style) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restrukturisasi nav sidebar jadi item datar + 2 section lipat (Alpine.js), gaya Nutrio, UI calm.

**Architecture:** Satu file Blade (`resources/views/layouts/navigation.blade.php`). Item datar (Dashboard, Motor Saya, Riding) tetap; sisanya jadi 2 grup collapsible ("Perawatan & Biaya", "Peta & Navigasi"). Toggle via Alpine (`x-data`/`x-bind`), animasi tinggi via trik CSS `grid-template-rows: 0fr↔1fr` (plugin `x-collapse` tidak tersedia). Grup yang memuat rute aktif terbuka otomatis saat load (nilai awal `x-data` di-set server-side).

**Tech Stack:** Laravel Blade, Alpine.js (sudah termuat), Tailwind (butuh `npm run build` untuk class arbitrary baru).

## Global Constraints

- Semua item nav sekarang tetap ada, tak boleh ada yang hilang. Footer "Sistem" + user-card tidak diubah.
- Tidak ada JS custom baru — pakai Alpine yang sudah ada.
- Hindari kombinasi `x-transition` + `x-cloak` (pernah bikin toggle stuck). Animasi pakai `grid-template-rows` + `x-bind:class`.
- UI calm: header grup redup (`text-slate-500`), chevron kecil `opacity-50`, anak diindentasi, state aktif pill `bg-primary-soft text-primary`.
- Grup aktif (memuat rute aktif) auto-expand saat load.
- Struktur final:
  - Datar: Dashboard (`dashboard`/gauge), Motor Saya (`motorcycles.index`,`motorcycles.*`/motorcycle), Riding (`riding`/play).
  - Grup **Perawatan & Biaya** (icon `wallet`): Biaya & Servis (`history`/wallet), BBM (`bbm.index`,`bbm.*`/droplet), Laporan (`laporan`/bar-chart).
  - Grup **Peta & Navigasi** (icon `map`): Peta Rute (`map.routes`/route), Titik Saya (`map.saved`/map-pin), Peta Komunitas (`map.community`/alert-triangle), Rencana Rute (`map.plans`/navigation).

---

### Task 1: Restrukturisasi navigation.blade.php jadi grup lipat

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php`

**Interfaces:**
- Produces: sidebar baru. Tidak ada konsumen lain (murni tampilan).

- [ ] **Step 1: Tulis ulang `navigation.blade.php`**

Ganti seluruh isi `resources/views/layouts/navigation.blade.php` dengan:

```blade
@php
    $flat = [
        ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'gauge'],
        ['route' => 'motorcycles.index', 'pattern' => 'motorcycles.*', 'label' => 'Motor Saya', 'icon' => 'motorcycle'],
        ['route' => 'riding', 'pattern' => 'riding', 'label' => 'Riding', 'icon' => 'play'],
    ];
    $groups = [
        [
            'label' => 'Perawatan & Biaya', 'icon' => 'wallet',
            'children' => [
                ['route' => 'history', 'pattern' => 'history', 'label' => 'Biaya & Servis', 'icon' => 'wallet'],
                ['route' => 'bbm.index', 'pattern' => 'bbm.*', 'label' => 'BBM', 'icon' => 'droplet'],
                ['route' => 'laporan', 'pattern' => 'laporan', 'label' => 'Laporan', 'icon' => 'bar-chart'],
            ],
        ],
        [
            'label' => 'Peta & Navigasi', 'icon' => 'map',
            'children' => [
                ['route' => 'map.routes', 'pattern' => 'map.routes', 'label' => 'Peta Rute', 'icon' => 'route'],
                ['route' => 'map.saved', 'pattern' => 'map.saved', 'label' => 'Titik Saya', 'icon' => 'map-pin'],
                ['route' => 'map.community', 'pattern' => 'map.community', 'label' => 'Peta Komunitas', 'icon' => 'alert-triangle'],
                ['route' => 'map.plans', 'pattern' => 'map.plans', 'label' => 'Rencana Rute', 'icon' => 'navigation'],
            ],
        ],
    ];
@endphp

<aside :class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 left-0 w-[260px] bg-surface border-r border-border flex flex-col z-50 transition-transform duration-200">
    {{-- Logo --}}
    <div class="h-16 flex items-center justify-between px-5 border-b border-border shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <div class="size-9 bg-primary rounded-xl flex items-center justify-center text-white">
                <x-icon.motorcycle class="w-5 h-5"/>
            </div>
            <div class="leading-none">
                <p class="font-heading font-bold text-foreground text-base tracking-tight">Muterin</p>
                <p class="text-[9px] font-bold text-primary uppercase tracking-[0.15em] mt-0.5">Motor Care</p>
            </div>
        </a>
        <button @click="mobileOpen = false" class="lg:hidden p-1.5 rounded-token text-muted-fg hover:bg-muted">
            <x-icon.x class="w-5 h-5"/>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
        {{-- Item datar --}}
        @foreach ($flat as $link)
            @php $active = request()->routeIs($link['pattern']); @endphp
            <a href="{{ route($link['route']) }}"
               class="flex items-center gap-3 px-3 h-10 rounded-xl text-[13px] transition {{ $active ? 'bg-primary-soft text-primary font-bold' : 'text-slate-500 hover:text-foreground hover:bg-muted font-medium' }}">
                <x-dynamic-component :component="'icon.'.$link['icon']" class="w-[17px] h-[17px] shrink-0"/>
                <span class="truncate">{{ $link['label'] }}</span>
            </a>
        @endforeach

        {{-- Grup lipat --}}
        @foreach ($groups as $group)
            @php $groupActive = collect($group['children'])->contains(fn ($c) => request()->routeIs($c['pattern'])); @endphp
            <div x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }" class="space-y-1 pt-1">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center gap-3 px-3 h-10 rounded-xl text-[13px] transition {{ $groupActive ? 'text-foreground font-semibold' : 'text-slate-500 hover:text-foreground hover:bg-muted font-medium' }}">
                    <x-dynamic-component :component="'icon.'.$group['icon']" class="w-[17px] h-[17px] shrink-0"/>
                    <span class="flex-1 text-left truncate">{{ $group['label'] }}</span>
                    <span class="shrink-0 transition-transform duration-200" :class="open ? 'rotate-0' : '-rotate-90'">
                        <x-icon.chevron-down class="w-4 h-4 opacity-50"/>
                    </span>
                </button>
                <div class="grid transition-[grid-template-rows] duration-200 ease-out" :class="open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                    <div class="overflow-hidden">
                        <div class="space-y-1">
                            @foreach ($group['children'] as $child)
                                @php $active = request()->routeIs($child['pattern']); @endphp
                                <a href="{{ route($child['route']) }}"
                                   class="flex items-center gap-3 pl-9 pr-3 h-9 rounded-xl text-[13px] transition {{ $active ? 'bg-primary-soft text-primary font-bold' : 'text-slate-500 hover:text-foreground hover:bg-muted font-medium' }}">
                                    <x-dynamic-component :component="'icon.'.$child['icon']" class="w-[15px] h-[15px] shrink-0"/>
                                    <span class="truncate">{{ $child['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </nav>

    {{-- Sistem --}}
    <div class="p-3 border-t border-border">
        <p class="px-3 mb-1.5 text-[10px] font-bold text-muted-fg uppercase tracking-[0.15em]">Sistem</p>
        <a href="{{ route('profile.edit') }}"
           class="flex items-center gap-3 px-3 h-9 rounded-xl text-[13px] font-medium transition {{ request()->routeIs('profile.edit') ? 'bg-primary-soft text-primary font-bold' : 'text-slate-500 hover:text-foreground hover:bg-muted' }}">
            <x-icon.wrench class="w-[16px] h-[16px] shrink-0"/>
            Pengaturan
        </a>

        {{-- User card --}}
        <div class="mt-3 flex items-center gap-3 p-2.5 bg-muted/60 rounded-xl">
            <div class="size-8 rounded-full bg-slate-800 text-white flex items-center justify-center font-heading font-bold text-xs shrink-0">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-foreground truncate">{{ Auth::user()->name }}</p>
                <p class="text-[10px] text-muted-fg truncate">{{ Auth::user()->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="p-1.5 rounded-lg text-muted-fg hover:text-accent hover:bg-accent/10 transition" title="Keluar">
                    <x-icon.logout class="w-4 h-4"/>
                </button>
            </form>
        </div>
    </div>
</aside>
```

- [ ] **Step 2: Build assets (class arbitrary baru)**

Kelas baru `grid-rows-[0fr]`, `grid-rows-[1fr]`, `transition-[grid-template-rows]`, `-rotate-90` muncul literal di Blade sehingga terscan Tailwind, tapi bundle harus di-build ulang (tak ada vite dev server).

Run: `npm run build`
Expected: build sukses, `public/build/assets/app-*.css` baru.

- [ ] **Step 3: Verifikasi manual di browser**

Login, buka beberapa halaman:
- Buka `/dashboard` → dua grup **tertutup** (tidak ada child aktif); Dashboard ter-highlight.
- Buka `/history` (Biaya & Servis) → grup **"Perawatan & Biaya" terbuka otomatis**, "Biaya & Servis" ter-highlight pill; grup "Peta & Navigasi" tertutup.
- Buka `/peta/komunitas` → grup **"Peta & Navigasi" terbuka otomatis**, "Peta Komunitas" ter-highlight.
- Klik header grup yang tertutup → terbuka dengan animasi tinggi halus, chevron muter; klik lagi → tertutup.
- Konfirmasi semua 10 item nav masih ada, "Riding" tetap datar, footer "Sistem" + user-card utuh.
- Cek konsol tidak ada error Alpine.

- [ ] **Step 4: Jalankan test suite**

Run: `php artisan test`
Expected: PASS semua (tidak ada file PHP tersentuh; perubahan murni Blade/CSS).

- [ ] **Step 5: Commit**

> Catatan: sesuai preferensi sesi ini, perubahan sengaja dibiarkan uncommitted untuk digabung user bersama rebrand Muterin. JANGAN commit kecuali user memintanya. Lewati step ini bila user masih menahan commit.

```bash
git add resources/views/layouts/navigation.blade.php public/build
git commit -m "feat: collapsible grouped sidebar (Nutrio-style), calm UI"
```

---

## Catatan penutup

- Perubahan satu file Blade + rebuild CSS. Tanpa backend, test suite tetap hijau.
- Verifikasi paling penting: auto-expand grup aktif saat load, animasi buka-tutup halus, tidak ada item nav yang hilang.
