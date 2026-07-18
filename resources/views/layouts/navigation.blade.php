@php
    $links = [
        ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'gauge'],
        ['route' => 'motorcycles.index', 'pattern' => 'motorcycles.*', 'label' => 'Motor', 'icon' => 'motorcycle'],
        ['route' => 'riding', 'pattern' => 'riding', 'label' => 'Riding', 'icon' => 'play'],
        ['route' => 'history', 'pattern' => 'history', 'label' => 'Riwayat', 'icon' => 'wallet'],
        ['route' => 'map', 'pattern' => 'map', 'label' => 'Peta', 'icon' => 'map-pin'],
    ];
@endphp
<nav x-data="{ open: false }" class="bg-surface border-b border-border sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-8">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-heading font-bold text-primary shrink-0">
                    <x-icon.motorcycle class="w-6 h-6"/> Amicta
                </a>

                <div class="hidden sm:flex sm:items-center sm:gap-1">
                    @foreach ($links as $link)
                        @php $active = request()->routeIs($link['pattern']); @endphp
                        <a href="{{ route($link['route']) }}"
                           class="flex items-center gap-1.5 px-3 py-2 rounded-token text-sm font-medium transition {{ $active ? 'bg-primary/10 text-primary' : 'text-muted-fg hover:text-foreground hover:bg-muted' }}">
                            <x-dynamic-component :component="'icon.'.$link['icon']" class="w-4 h-4"/>
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 px-3 py-2 rounded-token text-sm font-medium text-foreground hover:bg-muted transition">
                            {{ Auth::user()->name }}
                            <svg class="w-4 h-4 text-muted-fg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Profil</x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                Keluar
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="p-2 rounded-token text-muted-fg hover:bg-muted transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-border">
        <div class="px-2 pt-2 pb-3 space-y-1">
            @foreach ($links as $link)
                @php $active = request()->routeIs($link['pattern']); @endphp
                <a href="{{ route($link['route']) }}"
                   class="flex items-center gap-2 px-3 py-2.5 rounded-token text-sm font-medium {{ $active ? 'bg-primary/10 text-primary' : 'text-muted-fg' }}">
                    <x-dynamic-component :component="'icon.'.$link['icon']" class="w-4 h-4"/>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        <div class="pt-4 pb-3 border-t border-border px-4">
            <div class="font-medium text-foreground">{{ Auth::user()->name }}</div>
            <div class="text-sm text-muted-fg">{{ Auth::user()->email }}</div>

            <div class="mt-3 space-y-1">
                <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded-token text-sm text-muted-fg hover:bg-muted">Profil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();"
                       class="block px-3 py-2 rounded-token text-sm text-accent hover:bg-accent/10">Keluar</a>
                </form>
            </div>
        </div>
    </div>
</nav>
