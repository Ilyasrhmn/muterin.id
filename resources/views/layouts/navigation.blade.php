@php
    $links = [
        ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'gauge'],
        ['route' => 'motorcycles.index', 'pattern' => 'motorcycles.*', 'label' => 'Motor Saya', 'icon' => 'motorcycle'],
        ['route' => 'riding', 'pattern' => 'riding', 'label' => 'Riding', 'icon' => 'play'],
        ['route' => 'history', 'pattern' => 'history', 'label' => 'Biaya & Servis', 'icon' => 'wallet'],
        ['route' => 'bbm.index', 'pattern' => 'bbm.*', 'label' => 'BBM', 'icon' => 'droplet'],
        ['route' => 'laporan', 'pattern' => 'laporan', 'label' => 'Laporan', 'icon' => 'bar-chart'],
        ['route' => 'map.routes', 'pattern' => 'map.routes', 'label' => 'Peta Rute', 'icon' => 'route'],
        ['route' => 'map.pins', 'pattern' => 'map.pins', 'label' => 'Titik Saya', 'icon' => 'map-pin'],
        ['route' => 'map.plans', 'pattern' => 'map.plans', 'label' => 'Rencana Rute', 'icon' => 'navigation'],
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
                <p class="font-heading font-bold text-foreground text-base tracking-tight">Amicta</p>
                <p class="text-[9px] font-bold text-primary uppercase tracking-[0.15em] mt-0.5">Motor Care</p>
            </div>
        </a>
        <button @click="mobileOpen = false" class="lg:hidden p-1.5 rounded-token text-muted-fg hover:bg-muted">
            <x-icon.x class="w-5 h-5"/>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
        @foreach ($links as $link)
            @php $active = request()->routeIs($link['pattern']); @endphp
            <a href="{{ route($link['route']) }}"
               class="flex items-center gap-3 px-3 h-10 rounded-xl text-[13px] transition {{ $active ? 'bg-primary-soft text-primary font-bold' : 'text-slate-500 hover:text-foreground hover:bg-muted font-medium' }}">
                <x-dynamic-component :component="'icon.'.$link['icon']" class="w-[17px] h-[17px] shrink-0"/>
                <span class="truncate">{{ $link['label'] }}</span>
            </a>
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
