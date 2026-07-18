<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Amicta') }} — Rawat Motor Tanpa Lupa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-foreground">
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

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-border bg-surface">
        <div class="max-w-6xl mx-auto px-4 py-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-muted-fg">
            <span class="flex items-center gap-2 font-heading font-semibold text-foreground">
                <x-icon.motorcycle class="w-5 h-5 text-primary"/> Amicta
            </span>
            <span>&copy; {{ date('Y') }} Amicta. Rawat motor berbasis jarak tempuh asli.</span>
        </div>
    </footer>
</body>
</html>
