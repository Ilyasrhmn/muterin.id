# Landing Page Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild Amicta's landing page (`/`) so its content and structure fully represent the product's real feature set (odometer backbone, health score, attention center, documents, other expenses, cost report, GPS/maps) and looks like a professional marketing page, following Nutrio's structural/interaction patterns without copying its color palette.

**Architecture:** Pure Blade + Alpine.js + Tailwind, no new backend logic, no new routes, no new JS dependency. All interactivity (mobile nav, expandable feature cards, interactive dashboard preview, FAQ accordion) uses Alpine `x-data`/`x-show` the same way the rest of the app already does. Scroll reveal/count-up/parallax reuse the existing GSAP ScrollTrigger wiring in `resources/js/reveal.js` via `data-reveal`/`data-reveal-group`/`data-countup`/`data-parallax` attributes — untouched by this plan.

**Tech Stack:** Laravel 13 Blade components, Alpine.js, Tailwind CSS, GSAP ScrollTrigger (already wired, not modified).

## Global Constraints

- Design tokens (from `tailwind.config.js`): `primary` #0F766E, `primary-hover` #0B5D57, `primary-soft` #F0FDFA, `hero` #134E4A, `accent` #DC2626 (used only for main CTA buttons, unchanged), `status-green`/`status-yellow`/`status-red`. All new color usage must stay within the `primary`/`hero` teal family — no indigo, no colors outside this palette. The only gradient anywhere on the page is a `primary`→`hero` background wash in the hero section (explicitly approved) — no gradients elsewhere.
- Blade component conventions: `x-ui.card`, `x-ui.button` (variants: `primary`/`accent`/`outline`/`ghost`/`white`/`whiteOutline`), `x-ui.badge` (variants: `green`/`yellow`/`red`/`neutral`), `x-ui.progress` (`:percent`, `color`), `x-icon.*` (stroke-width 1.5, `viewBox="0 0 24 24"`, `$attributes->merge()` pattern — see any existing file in `resources/views/components/icon/` for the exact shape).
- No new JS/CSS dependency. All interactivity is Alpine `x-data`. No new npm packages.
- Route is unchanged: `Route::get('/', ...)` in `routes/web.php:16-18` already renders `view('landing')` for guests — do not touch this route.
- TDD: one growing feature test file (`tests/Feature/LandingPageTest.php`), one test method added per task asserting the new section's unique content is present via `$response->assertSee(...)`. Each assertion string must be genuinely new content that does not appear anywhere in the page before that task's change (verify this before writing the RED step).
- Commit directly to `master` (no worktree, no branch — established convention for this project).
- All Blade edits in this plan use exact "Find in current file / Replace with" blocks. Tasks 3–8 sequentially edit the same file (`resources/views/landing.blade.php`); Tasks 1–2 edit a different file (`resources/views/layouts/marketing.blade.php`). Execute tasks in numeric order — each task's "Find" text is drawn from a section no earlier task has touched.

---

### Task 1: Nav — section links + mobile menu

**Files:**
- Modify: `resources/views/layouts/marketing.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/LandingPageTest.php` (new file)

**Interfaces:**
- Produces: nav section anchors `#fitur` (already existed via hero button, unaffected), `#cara-kerja`, `#faq` (both new — later tasks add matching `id="cara-kerja"` / `id="faq"` sections in `landing.blade.php`; anchor links work with no error even before those ids exist since they're just fragment links).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LandingPageTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_has_section_links_and_mobile_menu(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('#cara-kerja', false);
        $response->assertSee('#faq', false);
        $response->assertSee('Buka menu', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LandingPageTest`
Expected: FAIL — `#cara-kerja`, `#faq`, and `Buka menu` do not appear anywhere in the current nav.

- [ ] **Step 3: Add scroll-behavior to app.css**

In `resources/css/app.css`, find:

```css
@layer base {
  body { @apply bg-background text-foreground font-sans antialiased; }
```

Replace with:

```css
@layer base {
  html { scroll-behavior: smooth; }
  body { @apply bg-background text-foreground font-sans antialiased; }
```

- [ ] **Step 4: Replace the nav in marketing.blade.php**

In `resources/views/layouts/marketing.blade.php`, find:

```blade
    <nav x-data="{ scrolled: false }" @scroll.window="scrolled = window.scrollY > 20"
        class="fixed top-0 inset-x-0 z-50 transition-colors duration-300"
        :class="scrolled ? 'bg-surface/90 backdrop-blur shadow-soft' : 'bg-transparent'">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-heading font-bold text-lg text-primary">
                <x-icon.motorcycle class="w-6 h-6"/> Amicta
            </a>
            <div class="flex items-center gap-2">
                <x-ui.button variant="ghost" href="{{ route('login') }}">Masuk</x-ui.button>
                <x-ui.button variant="primary" href="{{ route('register') }}">Daftar</x-ui.button>
            </div>
        </div>
    </nav>
```

Replace with:

```blade
    <nav x-data="{ scrolled: false, mobileOpen: false }" @scroll.window="scrolled = window.scrollY > 20"
        class="fixed top-0 inset-x-0 z-50 transition-colors duration-300"
        :class="scrolled || mobileOpen ? 'bg-surface/90 backdrop-blur shadow-soft' : 'bg-transparent'">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-heading font-bold text-lg text-primary">
                <x-icon.motorcycle class="w-6 h-6"/> Amicta
            </a>
            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-foreground">
                <a href="#fitur" class="hover:text-primary transition-colors">Fitur</a>
                <a href="#cara-kerja" class="hover:text-primary transition-colors">Cara Kerja</a>
                <a href="#faq" class="hover:text-primary transition-colors">FAQ</a>
            </div>
            <div class="hidden md:flex items-center gap-2">
                <x-ui.button variant="ghost" href="{{ route('login') }}">Masuk</x-ui.button>
                <x-ui.button variant="primary" href="{{ route('register') }}">Daftar</x-ui.button>
            </div>
            <button type="button" @click="mobileOpen = !mobileOpen" aria-label="Buka menu" class="md:hidden p-2 -mr-2 text-foreground">
                <x-icon.menu x-show="!mobileOpen" class="w-6 h-6"/>
                <x-icon.x x-show="mobileOpen" x-cloak class="w-6 h-6"/>
            </button>
        </div>
        <div x-show="mobileOpen" x-cloak x-transition class="md:hidden bg-surface border-t border-border px-4 py-4 space-y-1">
            <a href="#fitur" @click="mobileOpen = false" class="block text-sm font-medium text-foreground py-2">Fitur</a>
            <a href="#cara-kerja" @click="mobileOpen = false" class="block text-sm font-medium text-foreground py-2">Cara Kerja</a>
            <a href="#faq" @click="mobileOpen = false" class="block text-sm font-medium text-foreground py-2">FAQ</a>
            <div class="flex gap-2 pt-3">
                <x-ui.button variant="ghost" href="{{ route('login') }}" class="flex-1 justify-center">Masuk</x-ui.button>
                <x-ui.button variant="primary" href="{{ route('register') }}" class="flex-1 justify-center">Daftar</x-ui.button>
            </div>
        </div>
    </nav>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=LandingPageTest`
Expected: PASS (1 test)

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/marketing.blade.php resources/css/app.css tests/Feature/LandingPageTest.php
git commit -m "feat: add section links and mobile menu to marketing nav"
```

---

### Task 2: Footer — nav columns + wordmark

**Files:**
- Modify: `resources/views/layouts/marketing.blade.php`
- Test: `tests/Feature/LandingPageTest.php` (extend)

**Interfaces:**
- Consumes: `route('login')`, `route('register')` (existing named routes).

- [ ] **Step 1: Write the failing test**

In `tests/Feature/LandingPageTest.php`, add to the class:

```php
    public function test_footer_has_nav_columns_and_wordmark(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Navigasi', false);
        $response->assertSee('AMICTA', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LandingPageTest`
Expected: FAIL — `Navigasi` and the all-caps `AMICTA` wordmark don't exist yet (only the lowercase "Amicta" logo text exists, which doesn't satisfy the case-sensitive `assertSee('AMICTA')`).

- [ ] **Step 3: Replace the footer**

In `resources/views/layouts/marketing.blade.php`, find:

```blade
    <footer class="border-t border-border bg-surface">
        <div class="max-w-6xl mx-auto px-4 py-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-muted-fg">
            <span class="flex items-center gap-2 font-heading font-semibold text-foreground">
                <x-icon.motorcycle class="w-5 h-5 text-primary"/> Amicta
            </span>
            <span>&copy; {{ date('Y') }} Amicta. Rawat motor berbasis jarak tempuh asli.</span>
        </div>
    </footer>
```

Replace with:

```blade
    <footer class="border-t border-border bg-surface overflow-hidden">
        <div class="max-w-6xl mx-auto px-4 pt-14 pb-8">
            <div class="flex flex-col md:flex-row md:justify-between gap-10 pb-10 border-b border-border">
                <div class="max-w-sm">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 font-heading font-bold text-lg text-primary mb-3">
                        <x-icon.motorcycle class="w-6 h-6"/> Amicta
                    </a>
                    <p class="text-sm text-muted-fg">Rawat motor tanpa lupa, berbasis km yang benar-benar akurat — dari sumber mana saja: manual, isi bensin, servis, atau riding.</p>
                </div>
                <div class="flex gap-12 sm:gap-16">
                    <div>
                        <h4 class="text-xs font-bold text-foreground uppercase tracking-wider mb-4">Navigasi</h4>
                        <ul class="space-y-2.5 text-sm text-muted-fg">
                            <li><a href="#fitur" class="hover:text-primary transition-colors">Fitur</a></li>
                            <li><a href="#cara-kerja" class="hover:text-primary transition-colors">Cara Kerja</a></li>
                            <li><a href="#faq" class="hover:text-primary transition-colors">FAQ</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-foreground uppercase tracking-wider mb-4">Akun</h4>
                        <ul class="space-y-2.5 text-sm text-muted-fg">
                            <li><a href="{{ route('login') }}" class="hover:text-primary transition-colors">Masuk</a></li>
                            <li><a href="{{ route('register') }}" class="hover:text-primary transition-colors">Daftar</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <p class="text-[clamp(3rem,15vw,10rem)] leading-none font-heading font-black text-primary/10 text-center pt-8 select-none pointer-events-none">
                AMICTA
            </p>
            <p class="text-center text-xs text-muted-fg pt-4">&copy; {{ date('Y') }} Amicta. Rawat motor berbasis jarak tempuh asli.</p>
        </div>
    </footer>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LandingPageTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/marketing.blade.php
git commit -m "feat: rebuild marketing footer with nav columns and wordmark"
```

---

### Task 3: Hero section rewrite

**Files:**
- Modify: `resources/views/landing.blade.php`
- Test: `tests/Feature/LandingPageTest.php` (extend)

**Interfaces:**
- Consumes: `route('register')` (existing), `x-ui.button`, `x-ui.card`, `x-ui.badge`, `x-ui.progress`, `x-icon.play` (all existing components, unchanged).

- [ ] **Step 1: Write the failing test**

In `tests/Feature/LandingPageTest.php`, add:

```php
    public function test_hero_describes_multi_source_odometer_not_gps_only(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('km yang benar-benar akurat');
        $response->assertDontSee('Amicta merekam perjalananmu lewat GPS');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LandingPageTest`
Expected: FAIL — current hero headline doesn't contain "km yang benar-benar akurat", and the old GPS-only sentence is still present.

- [ ] **Step 3: Replace the hero section**

In `resources/views/landing.blade.php`, find (the entire hero `<section>` block, from `{{-- Hero --}}` through its closing `</section>`):

```blade
    {{-- Hero --}}
    <section class="relative overflow-hidden pt-32 pb-20 md:pt-40 md:pb-28">
        <div data-parallax class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-primary/10 blur-3xl pointer-events-none"></div>
        <div data-parallax class="absolute top-40 -left-24 w-72 h-72 rounded-full bg-accent/10 blur-3xl pointer-events-none"></div>

        <div class="max-w-6xl mx-auto px-4 grid md:grid-cols-2 gap-12 items-center relative">
            <div>
                <h1 data-reveal class="text-4xl md:text-5xl font-heading font-bold leading-tight text-foreground">
                    Rawat motor tanpa lupa,<br class="hidden md:block"> berbasis <span class="text-primary">jarak tempuh asli</span>.
                </h1>
                <p data-reveal class="mt-5 text-lg text-muted-fg max-w-lg">
                    Amicta merekam perjalananmu lewat GPS dan otomatis mengingatkan kapan oli, ban, aki, atau servis rutin motor perlu diganti — bukan tebak-tebakan.
                </p>
                <div data-reveal class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button variant="accent" size="lg" href="{{ route('register') }}">
                        <x-icon.play class="w-4 h-4"/> Mulai Gratis
                    </x-ui.button>
                    <x-ui.button variant="outline" size="lg" href="#fitur">Lihat Fitur</x-ui.button>
                </div>
            </div>

            <div data-reveal class="relative">
                <x-ui.card class="shadow-lift">
                    <div class="flex items-center justify-between mb-4">
                        <p class="font-heading font-semibold">Beat Merah</p>
                        <x-ui.badge variant="yellow">Mendekati batas</x-ui.badge>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Oli Mesin</span><span>2.100 / 2.500 km</span></div>
                            <x-ui.progress :percent="84" color="yellow"/>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Ban</span><span>4.200 / 12.000 km</span></div>
                            <x-ui.progress :percent="35" color="green"/>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Aki</span><span>3.000 / 15.000 km</span></div>
                            <x-ui.progress :percent="20" color="green"/>
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </section>
```

Replace with:

```blade
    {{-- Hero --}}
    <section class="relative overflow-hidden pt-32 pb-20 md:pt-40 md:pb-28">
        <div class="absolute inset-0 bg-gradient-to-br from-primary/15 via-transparent to-hero/10 pointer-events-none"></div>
        <div data-parallax class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-primary/10 blur-3xl pointer-events-none"></div>
        <div data-parallax class="absolute top-40 -left-24 w-72 h-72 rounded-full bg-hero/10 blur-3xl pointer-events-none"></div>

        <div class="max-w-6xl mx-auto px-4 grid md:grid-cols-2 gap-12 items-center relative">
            <div>
                <h1 data-reveal class="text-4xl md:text-5xl font-heading font-bold leading-tight text-foreground">
                    Rawat motor tanpa lupa,<br class="hidden md:block"> berbasis <span class="text-primary">km yang benar-benar akurat</span>.
                </h1>
                <p data-reveal class="mt-5 text-lg text-muted-fg max-w-lg">
                    Amicta mencatat jarak tempuh motormu dari sumber mana saja — manual, isi bensin, servis, atau riding — lalu otomatis mengingatkan kapan oli, ban, aki, atau servis rutin perlu diganti.
                </p>
                <div data-reveal class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button variant="accent" size="lg" href="{{ route('register') }}">
                        <x-icon.play class="w-4 h-4"/> Mulai Gratis
                    </x-ui.button>
                    <x-ui.button variant="outline" size="lg" href="#fitur">Lihat Fitur</x-ui.button>
                </div>
            </div>

            <div data-reveal class="relative">
                <x-ui.card class="shadow-lift">
                    <div class="flex items-center justify-between mb-4">
                        <p class="font-heading font-semibold">Beat Ilyas</p>
                        <x-ui.badge variant="yellow">Perlu Perhatian</x-ui.badge>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Oli Mesin</span><span>2.650 / 2.500 km</span></div>
                            <x-ui.progress :percent="106" color="red"/>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Ban</span><span>10.450 / 12.000 km</span></div>
                            <x-ui.progress :percent="87" color="yellow"/>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Aki</span><span>11.950 / 15.000 km</span></div>
                            <x-ui.progress :percent="80" color="yellow"/>
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </section>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LandingPageTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: rewrite hero to reflect multi-source odometer, not GPS-only"
```

---

### Task 4: Problem section copy + Stats strip

**Files:**
- Modify: `resources/views/landing.blade.php`
- Test: `tests/Feature/LandingPageTest.php` (extend)

**Interfaces:**
- Consumes: `x-ui.card`, `x-icon.bell`, `x-icon.gauge`, `x-icon.wrench` (existing, unchanged).

- [ ] **Step 1: Write the failing test**

In `tests/Feature/LandingPageTest.php`, add:

```php
    public function test_problem_and_stats_reflect_real_capabilities(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('nilai jual lebih tinggi');
        $response->assertSee('Sumber pencatatan km');
        $response->assertSee('Modul lengkap dalam satu aplikasi');
        $response->assertDontSee('Berbasis km asli via GPS');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LandingPageTest`
Expected: FAIL — the strengthened problem copy and new stats labels don't exist yet; the old "Berbasis km asli via GPS" stat label still does.

- [ ] **Step 3: Replace the Problem and Stats strip sections**

In `resources/views/landing.blade.php`, find (both sections together, from `{{-- Problem --}}` through the Stats strip's closing `</section>`):

```blade
    {{-- Problem --}}
    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-12">
            Masalah yang setiap pengendara motor kenal
        </h2>
        <div data-reveal-group class="grid md:grid-cols-3 gap-6">
            <x-ui.card>
                <div data-reveal class="w-11 h-11 rounded-token bg-accent/10 text-accent flex items-center justify-center mb-4">
                    <x-icon.bell class="w-6 h-6"/>
                </div>
                <p data-reveal class="font-heading font-semibold mb-1">Lupa ganti oli</p>
                <p data-reveal class="text-sm text-muted-fg">Patokannya cuma perasaan atau waktu, bukan jarak tempuh riil.</p>
            </x-ui.card>
            <x-ui.card>
                <div data-reveal class="w-11 h-11 rounded-token bg-accent/10 text-accent flex items-center justify-center mb-4">
                    <x-icon.gauge class="w-6 h-6"/>
                </div>
                <p data-reveal class="font-heading font-semibold mb-1">Odometer jarang dicek</p>
                <p data-reveal class="text-sm text-muted-fg">Harus buka motor & baca angka manual tiap mau tahu status.</p>
            </x-ui.card>
            <x-ui.card>
                <div data-reveal class="w-11 h-11 rounded-token bg-accent/10 text-accent flex items-center justify-center mb-4">
                    <x-icon.wrench class="w-6 h-6"/>
                </div>
                <p data-reveal class="font-heading font-semibold mb-1">Tidak ada riwayat servis</p>
                <p data-reveal class="text-sm text-muted-fg">Sulit dilacak kapan terakhir ganti apa, di km berapa, biayanya.</p>
            </x-ui.card>
        </div>
    </section>

    {{-- Stats strip --}}
    <section class="bg-primary text-white py-14">
        <div data-reveal-group class="max-w-6xl mx-auto px-4 grid grid-cols-3 gap-6 text-center">
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="4">0</span></p>
                <p class="text-sm text-white/80 mt-1">Komponen dipantau</p>
            </div>
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="100">0</span>%</p>
                <p class="text-sm text-white/80 mt-1">Berbasis km asli via GPS</p>
            </div>
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="0">0</span></p>
                <p class="text-sm text-white/80 mt-1">Odometer manual dicatat</p>
            </div>
        </div>
    </section>
```

Replace with:

```blade
    {{-- Problem --}}
    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-12">
            Masalah yang setiap pengendara motor kenal
        </h2>
        <div data-reveal-group class="grid md:grid-cols-3 gap-6">
            <x-ui.card>
                <div data-reveal class="w-11 h-11 rounded-token bg-accent/10 text-accent flex items-center justify-center mb-4">
                    <x-icon.bell class="w-6 h-6"/>
                </div>
                <p data-reveal class="font-heading font-semibold mb-1">Lupa ganti oli</p>
                <p data-reveal class="text-sm text-muted-fg">Patokannya cuma perasaan atau waktu, bukan jarak tempuh riil.</p>
            </x-ui.card>
            <x-ui.card>
                <div data-reveal class="w-11 h-11 rounded-token bg-accent/10 text-accent flex items-center justify-center mb-4">
                    <x-icon.gauge class="w-6 h-6"/>
                </div>
                <p data-reveal class="font-heading font-semibold mb-1">Odometer jarang dicek</p>
                <p data-reveal class="text-sm text-muted-fg">Harus buka motor & baca angka manual tiap mau tahu status.</p>
            </x-ui.card>
            <x-ui.card>
                <div data-reveal class="w-11 h-11 rounded-token bg-accent/10 text-accent flex items-center justify-center mb-4">
                    <x-icon.wrench class="w-6 h-6"/>
                </div>
                <p data-reveal class="font-heading font-semibold mb-1">Tidak ada riwayat servis</p>
                <p data-reveal class="text-sm text-muted-fg">Motor dengan riwayat servis lengkap dan tercatat rapi punya nilai jual lebih tinggi saat dijual — tapi kebanyakan orang gak pernah mencatatnya dari awal.</p>
            </x-ui.card>
        </div>
    </section>

    {{-- Stats strip --}}
    <section class="bg-primary text-white py-14">
        <div data-reveal-group class="max-w-6xl mx-auto px-4 grid grid-cols-3 gap-6 text-center">
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="4">0</span></p>
                <p class="text-sm text-white/80 mt-1">Sumber pencatatan km</p>
            </div>
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="9">0</span></p>
                <p class="text-sm text-white/80 mt-1">Modul lengkap dalam satu aplikasi</p>
            </div>
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="0">0</span></p>
                <p class="text-sm text-white/80 mt-1">Tebak-tebakan jadwal servis</p>
            </div>
        </div>
    </section>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LandingPageTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: strengthen problem copy and fix inaccurate stats strip claims"
```

---

### Task 5: Fitur — 4 expandable pillar cards

**Files:**
- Create: `resources/views/components/icon/chevron-down.blade.php`
- Modify: `resources/views/landing.blade.php`
- Test: `tests/Feature/LandingPageTest.php` (extend)

**Interfaces:**
- Consumes: `x-icon.check`, `x-icon.gauge`, `x-icon.bell`, `x-icon.wallet`, `x-icon.route` (all existing), `x-ui.card`, `x-ui.badge`.
- Produces: `<x-icon.chevron-down>` component, reused by Task 7's FAQ accordion.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/LandingPageTest.php`, add:

```php
    public function test_features_section_has_four_expandable_pillars(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Pantau Kondisi Motor');
        $response->assertSee('Jangan Ada yang Kelewat');
        $response->assertSee('Kontrol Biaya Penuh');
        $response->assertSee('Riding &amp; Peta Pribadi', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LandingPageTest`
Expected: FAIL — none of the 4 pillar titles exist yet (current page has 6 generic feature cards instead).

- [ ] **Step 3: Create the chevron-down icon**

`resources/views/components/icon/chevron-down.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'w-4 h-4', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
    <path d="m6 9 6 6 6-6"/>
</svg>
```

- [ ] **Step 4: Replace the Features section**

In `resources/views/landing.blade.php`, find (the entire Features `<section>`, from `{{-- Features --}}` through its closing `</section>`):

```blade
    {{-- Features --}}
    <section id="fitur" class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-4">Semua yang kamu butuh, satu aplikasi</h2>
        <p data-reveal class="text-center text-muted-fg max-w-xl mx-auto mb-12">Dari pencatatan otomatis sampai peta rute pribadi.</p>
        <div data-reveal-group class="grid md:grid-cols-3 gap-6">
            @php
                $features = [
                    ['icon' => 'gauge', 'title' => 'Trip Recording GPS', 'desc' => 'Nyalakan sebelum jalan, jarak tempuh terhitung otomatis lewat GPS.'],
                    ['icon' => 'wrench', 'title' => 'Status Warna Perawatan', 'desc' => 'Hijau, kuning, merah — tahu kapan harus servis sekali lihat.'],
                    ['icon' => 'motorcycle', 'title' => 'Multi-Motor', 'desc' => 'Kelola beberapa motor sekaligus dalam satu akun.'],
                    ['icon' => 'wallet', 'title' => 'Catatan Biaya', 'desc' => 'Setiap servis bisa dicatat biayanya, terlihat total per motor.'],
                    ['icon' => 'map-pin', 'title' => 'Peta Pribadi', 'desc' => 'Tandai jalan rawan, sepi, atau momen perjalananmu di peta.'],
                    ['icon' => 'bell', 'title' => 'Pengingat Otomatis', 'desc' => 'Notifikasi begitu status mendekati atau melewati batas aman.'],
                ];
            @endphp
            @foreach ($features as $f)
                <x-ui.card hover data-reveal>
                    <div class="w-11 h-11 rounded-token bg-primary/10 text-primary flex items-center justify-center mb-4">
                        <x-dynamic-component :component="'icon.'.$f['icon']" class="w-6 h-6"/>
                    </div>
                    <p class="font-heading font-semibold mb-1">{{ $f['title'] }}</p>
                    <p class="text-sm text-muted-fg">{{ $f['desc'] }}</p>
                </x-ui.card>
            @endforeach
        </div>
    </section>
```

Replace with:

```blade
    {{-- Features --}}
    <section id="fitur" class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-4">Semua yang kamu butuh, satu aplikasi</h2>
        <p data-reveal class="text-center text-muted-fg max-w-xl mx-auto mb-12">Empat pilar yang menyelesaikan masalah nyata pemilik motor — bukan sekadar pengingat.</p>
        <div data-reveal-group class="grid md:grid-cols-2 gap-6">
            @php
                $pillars = [
                    [
                        'icon' => 'gauge',
                        'title' => 'Pantau Kondisi Motor',
                        'summary' => 'Odometer akurat dari sumber mana saja, status warna tiap komponen, dan skor kesehatan motor dalam satu angka.',
                        'pills' => ['Odometer Backbone', 'Status Warna', 'Skor Kesehatan'],
                        'points' => [
                            'Odometer Backbone — km selalu update dari input manual, isi bensin, servis, atau riding, satu sumber kebenaran.',
                            'Status warna per komponen (hijau/kuning/merah) untuk oli, ban, aki, servis rutin.',
                            'Prediksi hari tersisa sebelum servis, berbasis rata-rata jarak harianmu.',
                            'Skor Kesehatan Motor 0-100, ringkasan sekali lihat.',
                            'Kelola beberapa motor sekaligus dalam satu akun.',
                        ],
                    ],
                    [
                        'icon' => 'bell',
                        'title' => 'Jangan Ada yang Kelewat',
                        'summary' => 'Semua yang butuh perhatianmu — servis, dokumen, sampai efisiensi BBM yang aneh — muncul di satu tempat.',
                        'pills' => ['Pusat Perhatian', 'Dokumen Kendaraan', 'Efisiensi BBM'],
                        'points' => [
                            'Pusat Perhatian menyatukan semua pengingat jadi satu daftar prioritas.',
                            'Reminder jatuh tempo Pajak STNK, Ganti Plat 5 Tahun, dan Asuransi.',
                            'Peringatan otomatis kalau efisiensi BBM tercatat gak masuk akal (indikasi salah input).',
                        ],
                    ],
                    [
                        'icon' => 'wallet',
                        'title' => 'Kontrol Biaya Penuh',
                        'summary' => 'Dari isi bensin sampai premi asuransi tahunan, semua pengeluaran motor kecatat dan terlihat totalnya.',
                        'pills' => ['BBM & Efisiensi', 'Riwayat Servis', 'Laporan TCO'],
                        'points' => [
                            'Catat isi bensin, hitung efisiensi km/liter otomatis.',
                            'Riwayat servis lengkap dengan nama bengkel, part yang diganti, dan foto nota.',
                            'Pengeluaran Lain — asuransi, parkir, cuci motor, aksesoris, dll.',
                            'Laporan Biaya Kepemilikan (TCO): total, biaya per km, tren bulanan.',
                        ],
                    ],
                    [
                        'icon' => 'route',
                        'title' => 'Riding & Peta Pribadi',
                        'summary' => 'Rekam perjalananmu lewat GPS dan tandai titik-titik penting di peta pribadimu.',
                        'pills' => ['GPS Trip', 'Peta Rute', 'Peta Titik'],
                        'points' => [
                            'Trip recording GPS — nyalakan sebelum jalan, jarak terhitung otomatis.',
                            'Peta rute — lihat kembali jalur yang pernah dilalui.',
                            'Peta titik — tandai lokasi penting (bengkel langganan, jalan rawan, dll).',
                            'Peta rencana — rencanakan rute sebelum berangkat.',
                        ],
                    ],
                ];
            @endphp
            @foreach ($pillars as $pillar)
                <div data-reveal x-data="{ open: false }">
                    <x-ui.card>
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 shrink-0 rounded-token bg-primary/10 text-primary flex items-center justify-center">
                                <x-dynamic-component :component="'icon.'.$pillar['icon']" class="w-6 h-6"/>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-heading font-semibold mb-1">{{ $pillar['title'] }}</p>
                                <p class="text-sm text-muted-fg mb-3">{{ $pillar['summary'] }}</p>
                                <div class="flex flex-wrap gap-1.5 mb-3">
                                    @foreach ($pillar['pills'] as $pill)
                                        <x-ui.badge variant="neutral">{{ $pill }}</x-ui.badge>
                                    @endforeach
                                </div>
                                <button type="button" @click="open = !open" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
                                    <span x-text="open ? 'Sembunyikan detail' : 'Lihat detail'"></span>
                                    <x-icon.chevron-down class="w-4 h-4 transition-transform" x-bind:class="open ? 'rotate-180' : ''"/>
                                </button>
                                <ul x-show="open" x-cloak x-transition class="mt-3 space-y-2">
                                    @foreach ($pillar['points'] as $point)
                                        <li class="flex items-start gap-2 text-sm text-muted-fg">
                                            <x-icon.check class="w-4 h-4 text-status-green shrink-0 mt-0.5"/>
                                            <span>{{ $point }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </x-ui.card>
                </div>
            @endforeach
        </div>
    </section>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=LandingPageTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/icon/chevron-down.blade.php resources/views/landing.blade.php
git commit -m "feat: group landing page features into 4 expandable pillar cards"
```

---

### Task 6: Interactive dashboard preview section

**Files:**
- Modify: `resources/views/landing.blade.php`
- Test: `tests/Feature/LandingPageTest.php` (extend)

**Interfaces:**
- Consumes: `x-icon.check`, `x-ui.button`, `x-ui.badge`, `x-ui.progress` (all existing).
- Produces: new section inserted between Features (Task 5) and "How it works", no id needed (not linked from nav).

- [ ] **Step 1: Write the failing test**

In `tests/Feature/LandingPageTest.php`, add:

```php
    public function test_dashboard_preview_section_shows_both_demo_motorcycles(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Lihat sendiri tampilannya');
        $response->assertSee('Skor 75');
        $response->assertSee('Skor 100');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LandingPageTest`
Expected: FAIL — no dashboard preview section exists yet.

- [ ] **Step 3: Insert the Dashboard Preview section**

In `resources/views/landing.blade.php`, find the closing of the Features section immediately followed by the How-it-works comment (this is the exact boundary between the two, unique in the file):

```blade
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
```

Replace with (inserts the new section between them, keeps both original lines intact):

```blade
            @endforeach
        </div>
    </section>

    {{-- Dashboard Preview --}}
    <section class="bg-muted py-16 md:py-24">
        <div class="max-w-6xl mx-auto px-4 grid lg:grid-cols-5 gap-12 items-center">
            <div data-reveal class="lg:col-span-2">
                <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4">Lihat sendiri tampilannya</h2>
                <ul class="space-y-4 mb-8">
                    <li class="flex items-start gap-3">
                        <x-icon.check class="w-5 h-5 text-status-green shrink-0 mt-0.5"/>
                        <span class="text-sm text-muted-fg">Status warna tiap komponen, sekali lihat langsung paham.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <x-icon.check class="w-5 h-5 text-status-green shrink-0 mt-0.5"/>
                        <span class="text-sm text-muted-fg">Skor Kesehatan Motor merangkum semua jadi satu angka.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <x-icon.check class="w-5 h-5 text-status-green shrink-0 mt-0.5"/>
                        <span class="text-sm text-muted-fg">Pusat Perhatian ngasih tau kalau ada yang mendesak.</span>
                    </li>
                </ul>
                <x-ui.button variant="primary" size="lg" href="{{ route('register') }}">Coba Sekarang</x-ui.button>
            </div>

            <div data-reveal class="lg:col-span-3" x-data="{ motor: 'beat' }">
                <div class="bg-surface border border-border rounded-2xl shadow-lift overflow-hidden">
                    <div class="border-b border-border px-5 py-3 flex items-center justify-between">
                        <div class="flex gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                        </div>
                        <div class="flex gap-1 bg-muted p-1 rounded-lg">
                            <button type="button" @click="motor = 'beat'" :class="motor === 'beat' ? 'bg-surface shadow-sm text-primary' : 'text-muted-fg'" class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">Beat Ilyas</button>
                            <button type="button" @click="motor = 'nmax'" :class="motor === 'nmax' ? 'bg-surface shadow-sm text-primary' : 'text-muted-fg'" class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">NMAX Kantor</button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div x-show="motor === 'beat'">
                            <div class="flex items-center justify-between mb-4">
                                <p class="font-heading font-semibold">Beat Ilyas</p>
                                <x-ui.badge variant="yellow">Perhatian &middot; Skor 75</x-ui.badge>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Oli Mesin</span><span>106%</span></div>
                                    <x-ui.progress :percent="106" color="red"/>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Ban</span><span>87%</span></div>
                                    <x-ui.progress :percent="87" color="yellow"/>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Aki</span><span>80%</span></div>
                                    <x-ui.progress :percent="80" color="yellow"/>
                                </div>
                            </div>
                        </div>
                        <div x-show="motor === 'nmax'" x-cloak>
                            <div class="flex items-center justify-between mb-4">
                                <p class="font-heading font-semibold">NMAX Kantor</p>
                                <x-ui.badge variant="green">Aman &middot; Skor 100</x-ui.badge>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Oli Mesin</span><span>16%</span></div>
                                    <x-ui.progress :percent="16" color="green"/>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Ban</span><span>51%</span></div>
                                    <x-ui.progress :percent="51" color="yellow"/>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Aki</span><span>41%</span></div>
                                    <x-ui.progress :percent="41" color="green"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LandingPageTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: add interactive dashboard preview section to landing page"
```

---

### Task 7: Cara Kerja copy + FAQ accordion

**Files:**
- Modify: `resources/views/landing.blade.php`
- Test: `tests/Feature/LandingPageTest.php` (extend)

**Interfaces:**
- Consumes: `x-icon.chevron-down` (from Task 5).

- [ ] **Step 1: Write the failing test**

In `tests/Feature/LandingPageTest.php`, add:

```php
    public function test_how_it_works_and_faq_reflect_multi_source_flow(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Catat km dari mana saja');
        $response->assertSee('Pertanyaan yang sering ditanyakan');
        $response->assertSee('Riwayat Awal');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LandingPageTest`
Expected: FAIL — the updated step title, the FAQ heading, and the FAQ answer mentioning "Riwayat Awal" don't exist yet.

- [ ] **Step 3: Replace the How it works section and add FAQ**

In `resources/views/landing.blade.php`, find (the entire How-it-works `<section>`, from `{{-- How it works --}}` through its closing `</section>`):

```blade
    {{-- How it works --}}
    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-12">Cara kerjanya</h2>
        <div data-reveal-group class="grid md:grid-cols-3 gap-8">
            @foreach ([
                ['n' => '1', 't' => 'Daftar motor', 'd' => 'Masukkan data motor & odometer awal, sekali saja.'],
                ['n' => '2', 't' => 'Nyalakan saat riding', 'd' => 'Tekan mulai sebelum jalan, selesai saat sampai.'],
                ['n' => '3', 't' => 'Dapat pengingat', 'd' => 'Amicta kasih tahu kapan waktunya servis.'],
            ] as $step)
                <div data-reveal class="text-center">
                    <div class="w-12 h-12 rounded-full bg-primary text-white font-heading font-bold flex items-center justify-center mx-auto mb-4">
                        {{ $step['n'] }}
                    </div>
                    <p class="font-heading font-semibold mb-1">{{ $step['t'] }}</p>
                    <p class="text-sm text-muted-fg">{{ $step['d'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
```

Replace with:

```blade
    {{-- How it works --}}
    <section id="cara-kerja" class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-12">Cara kerjanya</h2>
        <div data-reveal-group class="grid md:grid-cols-3 gap-8">
            @foreach ([
                ['n' => '1', 't' => 'Daftar motor', 'd' => 'Masukkan data motor & odometer awal. Motor bekas? Isi form Riwayat Awal sekali saja biar prediksi langsung akurat.'],
                ['n' => '2', 't' => 'Catat km dari mana saja', 'd' => 'Manual, pas isi bensin, pas servis, atau nyalakan GPS pas riding — bebas pilih, semua otomatis nyambung.'],
                ['n' => '3', 't' => 'Amicta yang mantau', 'd' => 'Status warna, skor kesehatan, dan Pusat Perhatian otomatis update, kamu tinggal cek kalau ada notifikasi.'],
            ] as $step)
                <div data-reveal class="text-center">
                    <div class="w-12 h-12 rounded-full bg-primary text-white font-heading font-bold flex items-center justify-center mx-auto mb-4">
                        {{ $step['n'] }}
                    </div>
                    <p class="font-heading font-semibold mb-1">{{ $step['t'] }}</p>
                    <p class="text-sm text-muted-fg">{{ $step['d'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="max-w-3xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-12">Pertanyaan yang sering ditanyakan</h2>
        <div data-reveal-group class="space-y-3" x-data="{ open: null }">
            @php
                $faqs = [
                    ['q' => 'Apakah saya harus selalu pakai GPS?', 'a' => 'Tidak. GPS cuma salah satu dari 4 cara mencatat km — manual, isi bensin, dan servis juga otomatis update odometer.'],
                    ['q' => 'Bisa buat lebih dari satu motor?', 'a' => 'Bisa, kelola semua motor dalam satu akun, gampang pindah motor aktif.'],
                    ['q' => 'Amicta gratis?', 'a' => 'Gratis, tanpa kartu kredit, daftar langsung bisa dipakai.'],
                    ['q' => 'Motor saya bekas, riwayat servisnya udah lama, gimana?', 'a' => 'Ada form "Riwayat Awal" opsional pas daftar motor — isi terakhir ganti oli/ban/aki/servis di km berapa, prediksi langsung akurat dari hari pertama.'],
                    ['q' => 'Data saya aman?', 'a' => 'Data motor & riwayatnya cuma bisa diakses dari akunmu sendiri, gak dibagikan ke pihak lain.'],
                ];
            @endphp
            @foreach ($faqs as $i => $faq)
                <div data-reveal class="bg-surface border border-border rounded-2xl overflow-hidden">
                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" class="w-full flex items-center justify-between gap-4 p-5 text-left">
                        <span class="font-heading font-semibold text-sm">{{ $faq['q'] }}</span>
                        <x-icon.chevron-down class="w-4 h-4 text-muted-fg shrink-0 transition-transform" x-bind:class="open === {{ $i }} ? 'rotate-180' : ''"/>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak x-transition class="px-5 pb-5 text-sm text-muted-fg">
                        {{ $faq['a'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </section>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LandingPageTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: update how-it-works copy and add FAQ accordion to landing page"
```

---

### Task 8: CTA trust badges + final verification

**Files:**
- Modify: `resources/views/landing.blade.php`
- Test: `tests/Feature/LandingPageTest.php` (extend)

**Interfaces:**
- Consumes: `x-icon.check` (existing).

- [ ] **Step 1: Write the failing test**

In `tests/Feature/LandingPageTest.php`, add:

```php
    public function test_cta_has_trust_badges(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Gratis selamanya');
        $response->assertSee('Setup di bawah 2 menit');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LandingPageTest`
Expected: FAIL — trust badges don't exist yet.

- [ ] **Step 3: Replace the CTA section**

In `resources/views/landing.blade.php`, find (the entire CTA `<section>`, from `{{-- CTA --}}` through its closing `</section>`, immediately followed by the closing `</x-marketing-layout>` tag):

```blade
    {{-- CTA --}}
    <section class="max-w-6xl mx-auto px-4 pb-24">
        <div data-reveal class="bg-primary rounded-token px-8 py-14 text-center text-white">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-3">Siap motor kamu selalu prima?</h2>
            <p class="text-white/85 mb-8 max-w-md mx-auto">Gratis, tanpa kartu kredit. Daftar sekarang dan tambahkan motor pertamamu.</p>
            <x-ui.button variant="accent" size="lg" href="{{ route('register') }}">Buat Akun Gratis</x-ui.button>
        </div>
    </section>

</x-marketing-layout>
```

Replace with:

```blade
    {{-- CTA --}}
    <section class="max-w-6xl mx-auto px-4 pb-24">
        <div data-reveal class="bg-hero rounded-token px-8 py-14 text-center text-white">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-3">Siap motor kamu selalu prima?</h2>
            <p class="text-white/85 mb-8 max-w-md mx-auto">Gratis, tanpa kartu kredit. Daftar sekarang dan tambahkan motor pertamamu.</p>
            <x-ui.button variant="accent" size="lg" href="{{ route('register') }}">Buat Akun Gratis</x-ui.button>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mt-8 text-xs font-medium text-white/70">
                <span class="flex items-center gap-1.5"><x-icon.check class="w-3.5 h-3.5"/> Gratis selamanya</span>
                <span class="flex items-center gap-1.5"><x-icon.check class="w-3.5 h-3.5"/> Tanpa kartu kredit</span>
                <span class="flex items-center gap-1.5"><x-icon.check class="w-3.5 h-3.5"/> Setup di bawah 2 menit</span>
            </div>
        </div>
    </section>

</x-marketing-layout>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LandingPageTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Full test suite + asset build**

Run: `php artisan test`
Expected: all pass (all pre-existing tests + 8 new `LandingPageTest` tests).

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 6: Manual verification**

Skip actual browser interaction in this step if running in a sandboxed subagent without browser tooling — note this as a concern in the report instead of skipping silently. If browser tooling is available: load `/` as a guest, verify — mobile menu opens/closes and links scroll to the right section, each of the 4 feature pillars expands/collapses, the dashboard preview switches between "Beat Ilyas" and "NMAX Kantor", each FAQ item expands independently, and the CTA trust badges are legible against the dark `hero` background.

- [ ] **Step 7: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: add trust badges to landing page CTA"
```

---
