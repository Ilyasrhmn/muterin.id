<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Muterin</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.pwa-head')

        <!-- Font Awesome 6 -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
              integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" 
              crossorigin="anonymous" referrerpolicy="no-referrer" />
    </head>
    <body class="bg-background text-foreground mtn-app">
        <a href="#main-content" class="mtn-skip-link">Langsung ke konten</a>
        <div x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false" class="min-h-dvh">
            {{-- Sidebar --}}
            @include('layouts.navigation')

            {{-- Mobile overlay --}}
            <div x-show="mobileOpen" x-cloak @click="mobileOpen = false"
                 x-transition.opacity class="fixed inset-0 bg-slate-900/40 z-40 lg:hidden"></div>

            {{-- Main --}}
            <div :inert="mobileOpen" class="lg:ml-[260px] min-h-dvh flex flex-col mtn-main-shell">
                <header class="h-16 bg-surface border-b border-border flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30 mtn-appbar">
                    <div class="flex items-center gap-3">
                        <button id="drawer-trigger" type="button" @click="mobileOpen = true; $nextTick(() => document.getElementById('drawer-close').focus())" :aria-expanded="mobileOpen" aria-controls="app-sidebar" aria-label="Buka navigasi" class="lg:hidden p-2 rounded-token text-muted-fg hover:bg-muted">
                            <x-icon.menu class="w-5 h-5"/>
                        </button>
                        @isset($header)
                            <div class="font-heading font-semibold text-foreground">{{ $header }}</div>
                        @endisset
                    </div>
                    <div class="flex items-center gap-2">
                    @include('partials.notification-center')
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @keydown.escape="open = false" :aria-expanded="open" aria-label="Menu akun" type="button"
                                class="size-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-heading font-bold text-sm hover:bg-primary/20 transition">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-2 w-56 bg-surface border border-border rounded-xl shadow-lift py-1.5 z-50">
                            <div class="px-3.5 py-2 border-b border-border">
                                <p class="text-sm font-bold text-foreground truncate">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-muted-fg truncate">{{ Auth::user()->email }}</p>
                            </div>
                            <a href="{{ route('profile.edit') }}"
                               class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-foreground hover:bg-muted transition">
                                <x-icon.wrench class="w-4 h-4 text-muted-fg"/> Edit Profil
                            </a>
                            <button type="button" data-open-subscription @click="open = false" class="w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-foreground hover:bg-muted transition text-left">
                                <x-icon.wallet class="w-4 h-4 text-muted-fg" aria-hidden="true"/> Upgrade paket
                            </button>
                            <button type="button" data-open-subscription data-subscription-tab="billing" @click="open = false" class="w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-foreground hover:bg-muted transition text-left">
                                <x-icon.clock class="w-4 h-4 text-muted-fg" aria-hidden="true"/> Kelola pembayaran
                            </button>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-accent hover:bg-accent/10 transition text-left">
                                    <x-icon.logout class="w-4 h-4"/> Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                    </div>
                </header>

                <main id="main-content" tabindex="-1" class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-ui.dialog />
        @include('partials.subscription-dialog')
        <script src="{{ asset('js/toast.js') }}?v={{ filemtime(public_path('js/toast.js')) }}"></script>
        @include('partials.toast-flash')
        <script src="{{ asset('js/geolocation.js') }}?v={{ filemtime(public_path('js/geolocation.js')) }}"></script>
        <script src="{{ asset('js/dialog.js') }}?v={{ filemtime(public_path('js/dialog.js')) }}"></script>
        <script src="{{ asset('js/pwa.js') }}?v={{ filemtime(public_path('js/pwa.js')) }}"></script>
        <script src="{{ asset('js/photo-picker.js') }}?v={{ filemtime(public_path('js/photo-picker.js')) }}"></script>
    </body>
</html>
