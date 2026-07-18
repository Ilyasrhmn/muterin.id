<x-app-layout>
    <x-slot name="header">Motor Saya</x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-heading font-bold text-xl text-foreground">Motor Saya</h1>
                <p class="text-sm text-muted-fg mt-0.5">Kelola motor dan pilih yang sedang dipakai.</p>
            </div>
            <x-ui.button variant="primary" href="{{ route('motorcycles.create') }}">
                <x-icon.plus class="w-4 h-4"/> Tambah Motor
            </x-ui.button>
        </div>

        @if (session('status'))
            <div class="p-3 rounded-token bg-status-green/10 text-status-green text-sm font-medium">{{ session('status') }}</div>
        @endif

        <div data-reveal-group class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($motorcycles as $motor)
                <div data-reveal class="bg-surface border border-border rounded-2xl shadow-soft hover:-translate-y-0.5 hover:shadow-lift transition duration-300 overflow-hidden {{ $motor->is_active ? 'ring-2 ring-primary' : '' }}">
                    <div class="p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div class="size-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                <x-icon.motorcycle class="w-6 h-6"/>
                            </div>
                            @if ($motor->is_active)
                                <x-ui.badge variant="green">Aktif</x-ui.badge>
                            @endif
                        </div>
                        <a href="{{ route('motorcycles.show', $motor) }}" class="font-heading font-bold text-foreground hover:text-primary">{{ $motor->nickname }}</a>
                        <p class="text-sm text-muted-fg">{{ $motor->brand }} {{ $motor->model }}</p>
                        <p class="text-xs text-muted-fg flex items-center gap-1.5 mt-3 tabular-nums">
                            <x-icon.gauge class="w-4 h-4"/> {{ number_format($motor->current_odometer_km) }} km
                        </p>
                    </div>
                    <div class="px-5 pb-5">
                        @unless ($motor->is_active)
                            <form method="POST" action="{{ route('motorcycles.activate', $motor) }}">
                                @csrf
                                <x-ui.button variant="outline" size="sm" type="submit" class="w-full">Jadikan Aktif</x-ui.button>
                            </form>
                        @else
                            <x-ui.button variant="ghost" size="sm" href="{{ route('motorcycles.show', $motor) }}" class="w-full">Lihat Detail</x-ui.button>
                        @endunless
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-surface border border-border rounded-2xl shadow-soft text-center py-14">
                    <div class="size-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mx-auto mb-4">
                        <x-icon.motorcycle class="w-7 h-7"/>
                    </div>
                    <p class="font-heading font-semibold text-foreground mb-1">Belum ada motor</p>
                    <p class="text-sm text-muted-fg mb-5">Tambahkan satu untuk mulai.</p>
                    <x-ui.button variant="primary" href="{{ route('motorcycles.create') }}">
                        <x-icon.plus class="w-4 h-4"/> Tambah Motor
                    </x-ui.button>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
