@php
    $groups = [
        'Menu' => [
            ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'gauge'],
            ['route' => 'motorcycles.index', 'pattern' => 'motorcycles.*', 'label' => 'Motor Saya', 'icon' => 'motorcycle'],
            ['route' => 'riding', 'pattern' => 'riding', 'label' => 'Riding', 'icon' => 'play'],
            ['route' => 'history', 'pattern' => 'history', 'label' => 'Riwayat', 'icon' => 'wallet'],
        ],
        'Peta' => [
            ['route' => 'map.routes', 'pattern' => 'map.routes', 'label' => 'Peta Rute', 'icon' => 'route'],
            ['route' => 'map.pins', 'pattern' => 'map.pins', 'label' => 'Titik Saya', 'icon' => 'map-pin'],
            ['route' => 'map.plans', 'pattern' => 'map.plans', 'label' => 'Rencana Rute', 'icon' => 'navigation'],
        ],
    ];
@endphp

<aside :class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 left-0 w-[260px] bg-surface border-r border-border flex flex-col z-50 transition-transform duration-200">
    {{-- Logo --}}
    <div class="h-16 flex items-center justify-between px-5 border-b border-border">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <div class="size-9 bg-gradient-to-br from-primary to-secondary rounded-token flex items-center justify-center text-white shadow-soft">
                <x-icon.motorcycle class="w-5 h-5"/>
            </div>
            <div class="leading-none">
                <p class="font-heading font-extrabold text-foreground text-lg tracking-tight">Amicta</p>
                <p class="text-[9px] font-bold text-primary uppercase tracking-[0.15em] mt-0.5">Motor Care</p>
            </div>
        </a>
        <button @click="mobileOpen = false" class="lg:hidden p-1.5 rounded-token text-muted-fg hover:bg-muted">
            <x-icon.x class="w-5 h-5"/>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto p-4 space-y-6">
        @foreach ($groups as $groupName => $links)
            <div class="space-y-1">
                <p class="px-3 mb-1 text-[10px] font-bold text-muted-fg uppercase tracking-[0.15em]">{{ $groupName }}</p>
                @foreach ($links as $link)
                    @php $active = request()->routeIs($link['pattern']); @endphp
                    <a href="{{ route($link['route']) }}"
                       class="flex items-center gap-3 px-3 h-10 rounded-token text-sm transition {{ $active ? 'bg-primary/10 text-primary font-semibold shadow-soft' : 'text-muted-fg hover:text-foreground hover:bg-muted font-medium' }}">
                        <x-dynamic-component :component="'icon.'.$link['icon']" class="w-[18px] h-[18px] shrink-0"/>
                        <span class="truncate">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- User card --}}
    <div class="p-4 border-t border-border">
        <div class="flex items-center gap-3 p-3 bg-muted/60 rounded-token">
            <div class="size-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-heading font-bold text-sm shrink-0">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-foreground truncate">{{ Auth::user()->name }}</p>
                <a href="{{ route('profile.edit') }}" class="text-[11px] text-muted-fg hover:text-primary">Lihat profil</a>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="p-1.5 rounded-token text-muted-fg hover:text-accent hover:bg-accent/10 transition" title="Keluar">
                    <x-icon.logout class="w-4 h-4"/>
                </button>
            </form>
        </div>
    </div>
</aside>
