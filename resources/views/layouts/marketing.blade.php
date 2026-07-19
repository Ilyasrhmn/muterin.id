<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Amicta') }} — Rawat Motor Tanpa Lupa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-foreground">
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

    <main>
        {{ $slot }}
    </main>

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
</body>
</html>
