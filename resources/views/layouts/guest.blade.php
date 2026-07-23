<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Muterin') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.pwa-head')
    </head>
    <body class="bg-background text-foreground">
        <div class="min-h-dvh grid md:grid-cols-2">
            <div class="relative hidden md:flex flex-col justify-between bg-primary text-white p-10 overflow-hidden">
                <div data-parallax class="absolute -top-20 -left-20 w-72 h-72 rounded-full bg-white/10 blur-3xl pointer-events-none"></div>
                <div data-parallax class="absolute bottom-10 right-0 w-56 h-56 rounded-full bg-accent/20 blur-3xl pointer-events-none"></div>

                <a href="{{ route('home') }}" class="relative flex items-center gap-2 font-heading font-bold text-xl">
                    <img src="{{ asset('images/muterin-logo.webp') }}" alt="Muterin" class="w-7 h-7 rounded-lg"> Muterin
                </a>
                <div class="relative">
                    <p class="text-2xl font-heading font-semibold leading-snug">
                        Rawat motor tanpa lupa,<br>berbasis jarak tempuh asli.
                    </p>
                    <p class="mt-3 text-white/80 max-w-sm">
                        Rekam perjalanan lewat GPS, dapat pengingat servis otomatis, kelola banyak motor sekaligus.
                    </p>
                </div>
                <p class="relative text-sm text-white/60">&copy; {{ date('Y') }} Muterin</p>
            </div>

            <div class="flex flex-col justify-center items-center px-6 py-12">
                <a href="{{ route('home') }}" class="md:hidden flex items-center gap-2 font-heading font-bold text-lg text-primary mb-8">
                    <img src="{{ asset('images/muterin-logo.webp') }}" alt="Muterin" class="w-6 h-6 rounded-md"> Muterin
                </a>
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>
        <script src="{{ asset('js/pwa.js') }}?v={{ filemtime(public_path('js/pwa.js')) }}"></script>
    </body>
</html>
