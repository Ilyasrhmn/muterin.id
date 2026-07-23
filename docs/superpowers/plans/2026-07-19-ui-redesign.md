# Muterin UI/UX Redesign  Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax. This is a **UI redesign of an already-working app**  the 39 existing tests must stay green; each task ends with a browser verification checkpoint instead of a new unit test.

**Goal:** Ubah tampilan Muterin dari template mentah Breeze menjadi UI light-theme yang profesional, konsisten, dan interaktif (smooth scroll Lenis + GSAP scroll animations), tanpa mengubah logika fitur yang sudah jalan.

**Architecture:** Tetap Laravel + Blade + Alpine + Tailwind (Vite). Tambah 2 dependency frontend: `lenis` (smooth scroll) & `gsap` (+ ScrollTrigger) via npm, di-import di `resources/js/app.js`. Design token dipusatkan di CSS variables + `tailwind.config.js`. Semua UI berulang jadi anonymous Blade components (`x-*`). Landing page publik baru di `/` sebagai etalase.

**Tech Stack:** Laravel 13, Blade, Alpine.js, Tailwind CSS, Vite, Lenis 1.x, GSAP 3.x (ScrollTrigger).

## Global Constraints

- **Tema:** Light-mode only (sesuai permintaan). TIDAK bikin dark mode.
- **Warna (design tokens, hex pasti):** primary `#0284C7`, primary-hover `#0369A1`, secondary `#0891B2`, accent/CTA `#DC2626`, accent-hover `#B91C1C`, background `#F0F9FF`, surface `#FFFFFF`, foreground `#0F172A`, muted-fg `#64748B`, border `#E0F0F8`, status-green `#22C55E`, status-yellow `#F59E0B`, status-red `#EF4444`. JANGAN pakai putih polos `#FFFFFF` sebagai background halaman (anti-pattern); putih hanya untuk surface/card.
- **Font:** Poppins (heading, 400-700), Open Sans (body, 300-700). Load via Google Fonts `display=swap`.
- **Radius:** soft  `--radius: 0.75rem` (rounded-xl) untuk card, `9999px` untuk pill/badge.
- **Motion:** durasi micro-interaction 150-300ms; scroll reveal 400-700ms `expo.out`/`power2.out`; parallax delta kecil (5-15%); SEMUA animasi dibungkus cek `prefers-reduced-motion` → kalau reduce, tampilkan konten final tanpa animasi. JANGAN pakai SplitText (plugin berbayar GSAP).
- **Ikon:** SVG saja (Lucide/Heroicons inline). TANPA emoji sebagai ikon.
- **Accessibility:** kontras teks ≥4.5:1; focus ring terlihat; touch target ≥44px; label form terlihat (bukan placeholder-only).
- **Fungsi tidak berubah:** semua nama route, nama field form, dan endpoint tetap sama. `php artisan test` harus tetap 39 passed setelah tiap task.
- **Reusable = komponen:** markup berulang WAJIB jadi `x-*` anonymous component, jangan copy-paste antar view.

---

## File Structure

```
resources/css/app.css              design tokens (CSS vars) + font import + base styles + utility layer
tailwind.config.js                 map warna/ font/ radius token ke Tailwind theme
resources/js/app.js                init Lenis + GSAP ScrollTrigger + reduced-motion guard
resources/js/reveal.js             helper: data-reveal, data-countup, data-parallax scroll behaviors

resources/views/components/
  ui/button.blade.php              variant: primary | accent | ghost | outline; size sm|md|lg
  ui/card.blade.php                surface card + optional hover-lift
  ui/badge.blade.php               variant: green|yellow|red|neutral (status pill)
  ui/stat-tile.blade.php           KPI tile (label, value, icon, optional trend) + count-up
  ui/input.blade.php               label + input + error + helper (form field)
  ui/nav-link.blade.php            (ganti bawaan Breeze) active-state on-brand
  ui/progress.blade.php            animated maintenance progress bar (ganti status-bar lama)
  icon/*.blade.php                 SVG icons (motorcycle, gauge, wrench, map-pin, bell, etc.)

resources/views/layouts/
  app.blade.php                    app shell (logged-in): topbar + brand + nav + content
  guest.blade.php                  auth shell: split-screen brand panel + form
  marketing.blade.php              landing shell: transparent sticky nav + footer

resources/views/landing.blade.php  NEW public landing (hero, problem, features, how-it-works, CTA)
resources/views/dashboard.blade.php  redesign: KPI row + per-motor cards
resources/views/motorcycles/*      redesign index/create/edit/show
resources/views/riding/index.blade.php  redesign: hero start/stop, big live readout
resources/views/history/index.blade.php redesign: timeline
resources/views/map/index.blade.php     restyle controls
resources/views/auth/*             restyle login/register/forgot/reset
```

---

## Task 0: Design Tokens, Fonts & Tailwind Theme

**Files:**
- Modify: `resources/css/app.css`, `tailwind.config.js`

**Interfaces:**
- Produces: CSS vars (`--color-primary` dst) + Tailwind color names (`bg-primary`, `text-foreground`, `border-border`, `bg-surface`, `text-status-red`…), font families `font-heading`/`font-sans`, `rounded-token`.

- [ ] **Step 1: Font + tokens di app.css**

Ganti isi `resources/css/app.css`:

```css
@import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap');
@tailwind base;
@tailwind components;
@tailwind utilities;

:root {
  --color-primary: #0284C7;
  --color-primary-hover: #0369A1;
  --color-secondary: #0891B2;
  --color-accent: #DC2626;
  --color-accent-hover: #B91C1C;
  --color-background: #F0F9FF;
  --color-surface: #FFFFFF;
  --color-foreground: #0F172A;
  --color-muted-fg: #64748B;
  --color-border: #E0F0F8;
  --color-green: #22C55E;
  --color-yellow: #F59E0B;
  --color-red: #EF4444;
  --radius: 0.75rem;
}

@layer base {
  body { @apply bg-background text-foreground font-sans antialiased; }
  h1, h2, h3, h4 { @apply font-heading; }
  :focus-visible { @apply outline-none ring-2 ring-primary ring-offset-2 ring-offset-background; }
}

/* Reveal: elemen mulai tersembunyi, JS menambah .is-visible. Jika reduced-motion, tampil langsung. */
@layer utilities {
  [data-reveal] { opacity: 0; transform: translateY(24px); }
  [data-reveal].is-visible { opacity: 1; transform: none; transition: opacity .6s ease-out, transform .6s ease-out; }
  @media (prefers-reduced-motion: reduce) {
    [data-reveal] { opacity: 1 !important; transform: none !important; transition: none !important; }
  }
}
```

- [ ] **Step 2: Map token ke Tailwind**

`tailwind.config.js`  di `theme.extend` tambahkan:

```js
extend: {
  colors: {
    primary: { DEFAULT: 'var(--color-primary)', hover: 'var(--color-primary-hover)' },
    secondary: 'var(--color-secondary)',
    accent: { DEFAULT: 'var(--color-accent)', hover: 'var(--color-accent-hover)' },
    background: 'var(--color-background)',
    surface: 'var(--color-surface)',
    foreground: 'var(--color-foreground)',
    'muted-fg': 'var(--color-muted-fg)',
    border: 'var(--color-border)',
    status: { green: 'var(--color-green)', yellow: 'var(--color-yellow)', red: 'var(--color-red)' },
  },
  fontFamily: {
    heading: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'],
    sans: ['"Open Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
  },
  borderRadius: { token: 'var(--radius)' },
  boxShadow: {
    soft: '0 1px 3px rgba(2,132,199,.06), 0 4px 16px rgba(2,132,199,.06)',
    lift: '0 8px 30px rgba(2,132,199,.12)',
  },
}
```

- [ ] **Step 3: Verifikasi build**

Run: `npm run build`
Expected: build sukses tanpa error, `public/build/manifest.json` ter-update.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat(ui): design tokens, fonts, tailwind theme (light)"
```

---

## Task 1: Motion Engine (Lenis + GSAP + reduced-motion)

**Files:**
- Modify: `resources/js/app.js`
- Create: `resources/js/reveal.js`
- Modify: `package.json` (via npm install)

**Interfaces:**
- Produces: smooth scroll global; auto-behavior untuk `[data-reveal]`, `[data-reveal-group]` (stagger children), `[data-countup]` (angka naik), `[data-parallax]` (bg parallax). Semua no-op saat reduced-motion.

- [ ] **Step 1: Install dependency**

```bash
npm install lenis gsap
```

- [ ] **Step 2: reveal.js**

`resources/js/reveal.js`:

```js
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export function initReveal() {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) {
    document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
    document.querySelectorAll('[data-countup]').forEach((el) => (el.textContent = el.dataset.countup));
    return;
  }

  // Single reveal
  document.querySelectorAll('[data-reveal]:not([data-reveal-group] [data-reveal])').forEach((el) => {
    ScrollTrigger.create({
      trigger: el, start: 'top 85%', once: true,
      onEnter: () => el.classList.add('is-visible'),
    });
  });

  // Staggered group
  document.querySelectorAll('[data-reveal-group]').forEach((group) => {
    const items = group.querySelectorAll('[data-reveal]');
    ScrollTrigger.create({
      trigger: group, start: 'top 80%', once: true,
      onEnter: () => items.forEach((el, i) => setTimeout(() => el.classList.add('is-visible'), i * 80)),
    });
  });

  // Count-up
  document.querySelectorAll('[data-countup]').forEach((el) => {
    const target = parseFloat(el.dataset.countup) || 0;
    ScrollTrigger.create({
      trigger: el, start: 'top 90%', once: true,
      onEnter: () => {
        const obj = { v: 0 };
        gsap.to(obj, { v: target, duration: 1.2, ease: 'power2.out',
          onUpdate: () => { el.textContent = Math.round(obj.v).toLocaleString('id-ID'); } });
      },
    });
  });

  // Parallax (bg/decorative only)
  document.querySelectorAll('[data-parallax]').forEach((el) => {
    gsap.to(el, { yPercent: 12, ease: 'none',
      scrollTrigger: { trigger: el.parentElement, scrub: true } });
  });
}
```

- [ ] **Step 3: app.js init Lenis + reveal**

Tambahkan di `resources/js/app.js` (setelah baris import bootstrap yang sudah ada):

```js
import Lenis from 'lenis';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { initReveal } from './reveal';

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!reduce) {
  const lenis = new Lenis({ duration: 1.1, smoothWheel: true });
  lenis.on('scroll', ScrollTrigger.update);
  gsap.ticker.add((time) => lenis.raf(time * 1000));
  gsap.ticker.lagSmoothing(0);
}

document.addEventListener('DOMContentLoaded', initReveal);
```

- [ ] **Step 4: Verifikasi build**

Run: `npm run build`
Expected: sukses, bundle memuat lenis + gsap tanpa error.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(ui): Lenis smooth scroll + GSAP reveal/countup/parallax engine"
```

---

## Task 2: Reusable UI Components (button, card, badge, stat-tile, input, progress, icons)

**Files:**
- Create: `resources/views/components/ui/*.blade.php`, `resources/views/components/icon/*.blade.php`

**Interfaces:**
- Produces (dipakai semua task berikut):
  - `<x-ui.button variant="primary|accent|ghost|outline" size="sm|md|lg" href="..."/>` (render `<a>` jika ada `href`, else `<button>`)
  - `<x-ui.card hover>` (slot; `hover` → hover-lift)
  - `<x-ui.badge variant="green|yellow|red|neutral"/>`
  - `<x-ui.stat-tile label value icon trend/>` (value pakai `data-countup`)
  - `<x-ui.input name label type value placeholder helper required/>` (auto tampil `@error`)
  - `<x-ui.progress :percent :color/>` (bar animasi width saat reveal)
  - `<x-icon.* class="..."/>` set SVG

- [ ] **Step 1: Button**

`resources/views/components/ui/button.blade.php`:

```blade
@props(['variant' => 'primary', 'size' => 'md', 'href' => null])
@php
    $base = 'inline-flex items-center justify-center gap-2 font-heading font-semibold rounded-token transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none cursor-pointer';
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-hover shadow-soft',
        'accent'  => 'bg-accent text-white hover:bg-accent-hover shadow-soft',
        'outline' => 'border border-border bg-surface text-foreground hover:bg-muted',
        'ghost'   => 'text-primary hover:bg-primary/10',
    ][$variant];
    $sizes = ['sm' => 'text-sm px-3 py-2', 'md' => 'text-sm px-4 py-2.5', 'lg' => 'text-base px-6 py-3'][$size];
    $classes = "$base $variants $sizes";
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
```

> Catatan: `bg-muted` dipakai di outline/ghost  tambahkan `muted: '#EFF7FB'` ke colors Tailwind di Task 0 Step 2 (tambahkan baris `muted: '#EFF7FB',`). Lakukan sekarang jika belum.

- [ ] **Step 2: Card**

`resources/views/components/ui/card.blade.php`:

```blade
@props(['hover' => false])
<div {{ $attributes->merge(['class' => 'bg-surface border border-border rounded-token shadow-soft p-5 '.($hover ? 'transition duration-200 hover:shadow-lift hover:-translate-y-0.5' : '')]) }}>
    {{ $slot }}
</div>
```

- [ ] **Step 3: Badge**

`resources/views/components/ui/badge.blade.php`:

```blade
@props(['variant' => 'neutral'])
@php
    $map = [
        'green'   => 'bg-status-green/15 text-status-green',
        'yellow'  => 'bg-status-yellow/15 text-[#B45309]',
        'red'     => 'bg-status-red/15 text-status-red',
        'neutral' => 'bg-muted text-muted-fg',
    ][$variant];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold $map"]) }}>
    {{ $slot }}
</span>
```

- [ ] **Step 4: Stat tile (KPI)**

`resources/views/components/ui/stat-tile.blade.php`:

```blade
@props(['label', 'value', 'icon' => null, 'suffix' => ''])
<x-ui.card class="flex items-center gap-4">
    @if ($icon)
        <div class="shrink-0 w-11 h-11 rounded-token bg-primary/10 text-primary flex items-center justify-center">
            {{ $icon }}
        </div>
    @endif
    <div>
        <p class="text-sm text-muted-fg">{{ $label }}</p>
        <p class="text-2xl font-heading font-bold text-foreground">
            <span data-countup="{{ $value }}">0</span>{{ $suffix }}
        </p>
    </div>
</x-ui.card>
```

- [ ] **Step 5: Input field**

`resources/views/components/ui/input.blade.php`:

```blade
@props(['name', 'label', 'type' => 'text', 'value' => '', 'placeholder' => '', 'helper' => null, 'required' => false])
<div class="space-y-1.5">
    <label for="{{ $name }}" class="block text-sm font-medium text-foreground">
        {{ $label }} @if ($required)<span class="text-accent">*</span>@endif
    </label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ old($name, $value) }}" placeholder="{{ $placeholder }}" @if($required) required @endif
        {{ $attributes->merge(['class' => 'w-full rounded-token border border-border bg-surface px-3.5 py-2.5 text-foreground placeholder:text-muted-fg/60 focus:border-primary focus:ring-2 focus:ring-primary/30 transition']) }}>
    @if ($helper)<p class="text-xs text-muted-fg">{{ $helper }}</p>@endif
    @error($name)<p class="text-xs text-accent">{{ $message }}</p>@enderror
</div>
```

- [ ] **Step 6: Progress bar (animasi)**

`resources/views/components/ui/progress.blade.php`:

```blade
@props(['percent' => 0, 'color' => 'green'])
@php $bg = ['green' => 'bg-status-green', 'yellow' => 'bg-status-yellow', 'red' => 'bg-status-red'][$color]; @endphp
<div class="w-full bg-muted rounded-full h-2 overflow-hidden" role="progressbar" aria-valuenow="{{ (int) min(100, $percent) }}" aria-valuemin="0" aria-valuemax="100">
    <div class="{{ $bg }} h-2 rounded-full transition-[width] duration-700 ease-out" style="width: {{ min(100, $percent) }}%"></div>
</div>
```

- [ ] **Step 7: Icon set (SVG, stroke 1.5, Lucide-style)**

Buat file per ikon di `resources/views/components/icon/`. Contoh `motorcycle.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="5.5" cy="16.5" r="3.5"/><circle cx="18.5" cy="16.5" r="3.5"/>
    <path d="M5.5 16.5h7l4-6h-9M15 10.5l1.5 3M8 6h3l2 4.5"/>
</svg>
```

Buat juga (pola SVG stroke 1.5 yang sama): `gauge.blade.php`, `wrench.blade.php`, `map-pin.blade.php`, `bell.blade.php`, `route.blade.php`, `wallet.blade.php`, `plus.blade.php`, `check.blade.php`, `play.blade.php`, `stop.blade.php`. (Ambil path resmi dari lucide.dev per nama ikon; jangan tebak path  copy dari Lucide.)

- [ ] **Step 8: Verifikasi (render sanity)**

Buat route sementara `/__ui` (hapus setelah cek) yang merender satu dari tiap komponen, `npm run build && php artisan serve`, buka `/__ui`, pastikan semua muncul rapi. Lalu hapus route-nya.

- [ ] **Step 9: Commit**

```bash
git add -A && git commit -m "feat(ui): reusable Blade UI component library + icon set"
```

---

## Task 3: Landing Page (etalase  Lenis + scroll reveals)

**Files:**
- Create: `resources/views/layouts/marketing.blade.php`, `resources/views/landing.blade.php`
- Modify: `routes/web.php` (root `/` → landing publik; redirect ke dashboard jika sudah login)
- Modify: `tests/Feature/ExampleTest.php` (root sekarang 200 untuk guest, bukan redirect)

**Interfaces:**
- Consumes: `x-ui.button`, `x-icon.*`, `[data-reveal]`, `[data-parallax]`, `[data-countup]`.
- Produces: halaman marketing publik; guest lihat landing, user login diarahkan ke dashboard.

- [ ] **Step 1: Route root**

Ganti closure `/` di `routes/web.php`:

```php
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('landing');
})->name('home');
```

- [ ] **Step 2: Update ExampleTest**

`tests/Feature/ExampleTest.php`  ganti isi test jadi dua kasus:

```php
public function test_guest_sees_landing_page(): void
{
    $this->get('/')->assertOk()->assertSee('Muterin');
}

public function test_logged_in_user_is_redirected_to_dashboard(): void
{
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
}
```

- [ ] **Step 3: Marketing layout**

`resources/views/layouts/marketing.blade.php`: `<html>` shell dengan `@vite`, sticky top nav transparan (logo "Muterin" + tombol "Masuk" `x-ui.button variant=ghost href=login` & "Daftar" `variant=primary href=register`), `{{ $slot }}`, footer sederhana. Nav jadi solid (`bg-surface/90 backdrop-blur`) setelah scroll (Alpine: `x-data="{ y:0 }" @scroll.window="y=window.scrollY"` → `:class="y>20 && 'bg-surface/90 backdrop-blur shadow-soft'"`).

- [ ] **Step 4: Landing sections**

`resources/views/landing.blade.php` pakai `<x-layouts.marketing>` dengan urutan section (pola "Hero + Problem + Solution + Features + CTA"):

1. **Hero**: headline besar (Poppins) "Rawat motor tanpa lupa, berbasis jarak tempuh asli." + subteks + CTA ganda ("Mulai Gratis" accent + "Lihat Fitur" ghost) + panel mockup dashboard di kanan (screenshot/ilustrasi) dengan `data-parallax` pada blob dekoratif di belakang. Beri `data-reveal` pada headline, subteks, CTA berurutan.
2. **Problem** (`data-reveal`): 3 poin nyeri (lupa ganti oli, odometer jarang dicek, gak ada riwayat) sebagai kartu ikon.
3. **Stats strip**: 3 angka `data-countup` (mis. "4 komponen dipantau", "100% berbasis km asli", dst) di atas `bg-primary` teks putih.
4. **Features** (`data-reveal-group` stagger): grid 3-kolom kartu fitur (GPS trip auto, status warna, multi-motor, biaya, peta rawan, PDF) tiap kartu `x-ui.card hover` + `x-icon`.
5. **How it works**: 3 langkah bernomor (Daftar motor → Nyalakan saat riding → Dapat pengingat).
6. **CTA penutup**: banner `bg-primary` + tombol "Buat Akun Gratis".

Setiap section `max-w-6xl mx-auto px-4 py-16 md:py-24`.

- [ ] **Step 5: Verifikasi**

Run: `npm run build`, `php artisan test --filter=ExampleTest` (PASS), lalu `php artisan serve` → buka `/` sebagai guest: scroll harus smooth (Lenis), section muncul ber-reveal, angka count-up. Uji juga `prefers-reduced-motion` (DevTools → Rendering → Emulate CSS prefers-reduced-motion: reduce) → semua konten tampil tanpa animasi.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(ui): public landing page with Lenis smooth scroll and GSAP reveals"
```

---

## Task 4: Auth Pages Redesign (split-screen brand)

**Files:**
- Modify: `resources/views/layouts/guest.blade.php`, `resources/views/auth/login.blade.php`, `register.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php`

**Interfaces:**
- Consumes: `x-ui.input`, `x-ui.button`.
- Produces: auth pages branded, konsisten token. Nama field & action form TIDAK berubah (test auth tetap hijau).

- [ ] **Step 1: Guest layout split-screen**

`resources/views/layouts/guest.blade.php`: grid 2 kolom di desktop  kiri panel `bg-primary` (brand "Muterin", tagline, ilustrasi/pattern halus, `data-parallax` blob), kanan `bg-background` berisi `{{ $slot }}` (card form center, `max-w-md`). Mobile: panel brand jadi header tipis di atas.

- [ ] **Step 2: Login/Register pakai komponen**

Ganti field mentah Breeze di `login.blade.php` & `register.blade.php` dengan `<x-ui.input name="email" label="Email" type="email" required/>` dst, tombol submit `<x-ui.button variant="primary" class="w-full">`. **PENTING: pertahankan `name="email"`, `name="password"`, `name="password_confirmation"`, `wire`/action & `@csrf` persis seperti semula.** Link "Lupa password?" & "Sudah punya akun?" tetap ada.

- [ ] **Step 3: forgot & reset**

Terapkan pola komponen yang sama ke `forgot-password.blade.php` & `reset-password.blade.php`.

- [ ] **Step 4: Verifikasi**

Run: `php artisan test --filter=Auth` (semua PASS  memastikan field/flow tak berubah), lalu manual: buka `/login` & `/register`, submit, pastikan redirect benar.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(ui): branded split-screen auth pages"
```

---

## Task 5: App Shell + Navigation Redesign

**Files:**
- Modify: `resources/views/layouts/app.blade.php`, `resources/views/layouts/navigation.blade.php`

**Interfaces:**
- Consumes: `x-icon.*`.
- Produces: topbar konsisten dengan brand, nav links (Dashboard, Motor, Riding, Riwayat, Peta) dengan active-state on-brand + ikon, user dropdown; mobile: hamburger → panel.

- [ ] **Step 1: Redesign navigation.blade.php**

Ganti nav Breeze: brand "Muterin" (logo `x-icon.motorcycle` + wordmark Poppins) di kiri; menu tengah/kanan pakai link dengan ikon + label; active pakai `text-primary` + garis bawah/`bg-primary/10 rounded`. Gunakan warna token (`text-muted-fg` default, `text-primary` aktif). Pertahankan semua `route()` yang sudah ada + form logout.

- [ ] **Step 2: app.blade.php shell**

Pastikan `<body class="bg-background">`, header `bg-surface border-b border-border`, konten `min-h-dvh`. Slot `$header` pakai `font-heading`.

- [ ] **Step 3: Verifikasi**

Manual: login, cek tiap menu  active state pindah benar, responsif di 375px (hamburger jalan).

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat(ui): branded app shell and navigation with active states"
```

---

## Task 6: Dashboard Redesign (Executive Dashboard)

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php` (tambah agregat KPI), `resources/views/dashboard.blade.php`

**Interfaces:**
- Consumes: `x-ui.stat-tile`, `x-ui.card`, `x-ui.progress`, `x-ui.badge`, `x-icon.*`.
- Produces: baris KPI (jumlah motor, total km semua motor, item perlu perhatian, total biaya perawatan) + kartu per-motor dengan progress animasi & badge status.

- [ ] **Step 1: Controller agregat**

Di `DashboardController::__invoke`, selain `$dashboard`, hitung:

```php
$kpi = [
    'motor_count' => $motorcycles->count(),
    'total_km' => $motorcycles->sum('current_odometer_km'),
    'attention' => $dashboard->sum(fn ($r) => $r['items']->filter(fn ($i) => $i['status']['color'] !== 'green')->count()),
    'total_cost' => \App\Models\MaintenanceLog::whereHas('item.motorcycle', fn ($q) => $q->where('user_id', auth()->id()))->sum('cost'),
];
return view('dashboard', ['dashboard' => $dashboard, 'kpi' => $kpi]);
```

- [ ] **Step 2: KPI row + motor cards**

Redesign `dashboard.blade.php`: grid `sm:grid-cols-2 lg:grid-cols-4` berisi 4 `x-ui.stat-tile` (`data-reveal-group`), lalu daftar motor pakai `x-ui.card hover`  tiap item perawatan pakai `x-ui.progress :percent="$i['status']['percent']" :color="$i['status']['color']"` + label. Badge "Perlu perhatian" pakai `x-ui.badge variant="red"`. Empty state ramah (ikon + ajakan tambah motor + `x-ui.button`). Tetap sertakan `<script src="{{ asset('js/notify.js') }}">` (data-attribute status di progress/row tetap ada  pertahankan `data-item-id/name/color`).

- [ ] **Step 3: Verifikasi**

Manual: dashboard dengan ≥1 motor → KPI count-up jalan, progress bar animasi, warna status benar. Cek 375px.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat(ui): executive-style dashboard with KPI tiles and animated status"
```

---

## Task 7: Motorcycles Pages Redesign

**Files:**
- Modify: `resources/views/motorcycles/index.blade.php`, `create.blade.php`, `edit.blade.php`, `show.blade.php`

**Interfaces:**
- Consumes: `x-ui.card`, `x-ui.button`, `x-ui.input`, `x-ui.badge`, `x-ui.progress`, `x-icon.*`.

- [ ] **Step 1: Index**  grid kartu motor (`x-ui.card hover`), tiap kartu: nama (Poppins), meta, odometer, badge status ringkas, tombol "Jadikan Aktif" (`x-ui.button variant=outline size=sm`) / label "Aktif" (`x-ui.badge green`). Header dengan judul + `x-ui.button variant=primary href=create` beri ikon plus. Empty state ramah.
- [ ] **Step 2: Create/Edit**  form dalam `x-ui.card max-w-lg`, semua field `x-ui.input`, submit `x-ui.button`. Pertahankan `name` field & action/`@method`.
- [ ] **Step 3: Show**  header motor + tombol Edit; tiap item perawatan `x-ui.card` berisi `x-ui.progress` + tombol "Tandai selesai" (Alpine toggle) + form biaya pakai `x-ui.input`. Link "Cari Bengkel" saat merah pakai `x-ui.button variant=accent size=sm`. Pertahankan semua route/field.
- [ ] **Step 4: Verifikasi**  manual CRUD penuh: tambah, edit, aktifkan, tandai servis; pastikan berfungsi & rapi di 375px.
- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(ui): redesign motorcycle index/create/edit/show"
```

---

## Task 8: Riding Page Redesign

**Files:**
- Modify: `resources/views/riding/index.blade.php`

**Interfaces:**
- Consumes: `x-ui.card`, `x-ui.button`, `x-icon.play/stop`. TIDAK mengubah `trip-recorder.js` maupun id elemen (`motor-select`, `distance`, `duration`, `start-btn`, `stop-btn`, `gps-msg`)  hanya styling.

- [ ] **Step 1: Restyle**  kartu tengah `x-ui.card` dengan readout jarak besar (Poppins `text-6xl`, `tabular-nums`), durasi di bawah, select motor bergaya, tombol Start (`x-ui.button variant=primary size=lg w-full` + ikon play) & Stop (`variant=accent size=lg`). Pesan GPS `text-accent`. **JANGAN ubah `id=` elemen** (JS bergantung padanya). Empty state (belum ada motor) tetap.
- [ ] **Step 2: Verifikasi**  DevTools Sensors set Location, Start→gerak→Stop, pastikan angka jalan & redirect ke dashboard (fungsi utuh).
- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "feat(ui): redesign riding tracker screen"
```

---

## Task 9: History (timeline) + Map Restyle

**Files:**
- Modify: `resources/views/history/index.blade.php`, `resources/views/map/index.blade.php`

**Interfaces:**
- Consumes: `x-ui.card`, `x-ui.button`, `x-ui.badge`, `x-icon.*`.

- [ ] **Step 1: History timeline**  dua kolom (Perjalanan, Perawatan) jadi timeline: tiap entri kartu dengan garis/titik di kiri, ikon kategori, tanggal `tabular-nums`. Total biaya sebagai `x-ui.stat-tile` kecil. Tombol "Export PDF" `x-ui.button variant=outline`. `[data-reveal]` per entri. Pertahankan route `history.export`.
- [ ] **Step 2: Map controls**  bungkus kontrol mode + tombol "Simpan Rencana" dalam `x-ui.card` bar di atas peta; select bergaya token; legenda warna kategori (momen/rawan/sepi) kecil. **JANGAN ubah `id="map"`, `id="mode"`, `id="save-plan"`, `@csrf`** (map.js bergantung). Peta tetap Leaflet.
- [ ] **Step 3: Verifikasi**  manual: history tampil rapi & export PDF jalan; `/map` render, tambah pin & simpan rencana masih berfungsi.
- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat(ui): timeline history and restyled map controls"
```

---

## Task 10: Polish Pass (a11y, responsive, reduced-motion, cleanup)

**Files:** lintas view (perbaikan kecil), hapus artefak sementara.

- [ ] **Step 1: Reduced-motion audit**  dengan emulate reduce: landing, dashboard, semua reveal/countup/parallax tampil instan tanpa gerak; Lenis nonaktif (native scroll). Perbaiki bila ada yang tetap animasi.
- [ ] **Step 2: Responsive audit**  cek 375 / 768 / 1024 / 1440 tiap halaman: tanpa horizontal scroll, touch target ≥44px, nav mobile jalan.
- [ ] **Step 3: Kontras & focus**  pastikan teks muted `#64748B` di atas surface ≥4.5:1 (ganti ke `#475569` bila kurang di konteks kecil); semua elemen interaktif punya focus ring; badge kuning pakai teks gelap (`#B45309`) bukan kuning-di-putih.
- [ ] **Step 4: Full test + build**  `php artisan test` (harus 39 passed) + `npm run build` sukses. Hapus route `/__ui` bila masih ada.
- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "chore(ui): accessibility, responsive and reduced-motion polish"
```

---

## Self-Review (dicek terhadap design system)

- **Coverage:** tokens+font (T0), motion Lenis/GSAP (T1), komponen (T2), landing etalase (T3), auth (T4), shell+nav (T5), dashboard (T6), motor CRUD (T7), riding (T8), history+map (T9), polish (T10). Semua permintaan user tercakup: tema cerah ✓, Lenis ✓, interaktif/scroll ✓, profesional ✓, fokus UX ✓.
- **Fungsi aman:** setiap task yang menyentuh view berfitur menegaskan "pertahankan route/field/id JS"; gate tiap task menjalankan test terkait; T10 memastikan 39 test tetap hijau. Perubahan perilaku satu-satunya yang disengaja: root `/` jadi landing (ExampleTest diperbarui).
- **Konsistensi token:** semua warna via nama Tailwind token (bukan hex mentah di view); satu skala radius/shadow; ikon SVG stroke 1.5 seragam.
- **Motion aman:** semua efek punya jalur reduced-motion; parallax hanya elemen dekoratif; tanpa SplitText berbayar.
- **Catatan:** `muted: '#EFF7FB'` harus ditambahkan ke colors Tailwind (disebut di T0 & T2)  pastikan ada sebelum T2.
```
