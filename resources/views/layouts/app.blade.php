<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Amicta') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-background text-foreground">
        <div x-data="{ mobileOpen: false }" class="min-h-dvh">
            {{-- Sidebar --}}
            @include('layouts.navigation')

            {{-- Mobile overlay --}}
            <div x-show="mobileOpen" x-cloak @click="mobileOpen = false"
                 x-transition.opacity class="fixed inset-0 bg-slate-900/40 z-40 lg:hidden"></div>

            {{-- Main --}}
            <div class="lg:ml-[260px] min-h-dvh flex flex-col">
                <header class="h-16 bg-surface border-b border-border flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30">
                    <div class="flex items-center gap-3">
                        <button @click="mobileOpen = true" class="lg:hidden p-2 rounded-token text-muted-fg hover:bg-muted">
                            <x-icon.menu class="w-5 h-5"/>
                        </button>
                        @isset($header)
                            <div class="font-heading font-semibold text-foreground">{{ $header }}</div>
                        @endisset
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative hidden md:block">
                            <x-icon.search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-fg"/>
                            <input type="text" placeholder="Cari…"
                                   class="h-9 w-56 pl-9 pr-4 rounded-full bg-muted border-transparent text-sm focus:bg-surface focus:border-primary focus:ring-2 focus:ring-primary/20 transition outline-none">
                        </div>
                        <div class="size-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-heading font-bold text-sm">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                    </div>
                </header>

                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-ui.dialog />
        <script src="{{ asset('js/dialog.js') }}"></script>
    </body>
</html>

