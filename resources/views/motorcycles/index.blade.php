<x-app-layout>
    <x-slot name="header">Motor Saya</x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">

        <x-ui.hero badge="{{ $motorcycles->count() }} motor terdaftar" title="Motor Saya"
                    subtitle="Kelola semua motormu di sini dan pilih mana yang sedang kamu pakai riding.">
            <x-slot:side>
                <x-ui.button variant="white" href="{{ route('motorcycles.create') }}">Tambah Motor</x-ui.button>
            </x-slot:side>
        </x-ui.hero>

        @if (session('status'))
            <div class="p-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-medium">{{ session('status') }}</div>
        @endif

        <div data-reveal-group class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($motorcycles as $motor)
                <div data-reveal class="bg-surface border rounded-2xl overflow-hidden {{ $motor->is_active ? 'border-primary/40' : 'border-border' }}">
                    <div class="p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div class="size-11 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                                <x-icon.motorcycle class="w-6 h-6"/>
                            </div>
                            @if ($motor->is_active)
                                <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700">Aktif</span>
                            @endif
                        </div>
                        <a href="{{ route('motorcycles.show', $motor) }}" class="font-heading font-bold text-foreground hover:text-primary">{{ $motor->nickname }}</a>
                        <p class="text-sm text-muted-fg">{{ $motor->brand }} {{ $motor->model }}</p>
                        @if ($motor->plat_nomor)
                            <span class="inline-block mt-2 text-[11px] font-bold font-heading tracking-wider text-foreground bg-muted px-2 py-1 rounded-md border border-border">{{ $motor->plat_nomor }}</span>
                        @endif
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
                <div class="col-span-full bg-surface border border-border rounded-2xl text-center py-14">
                    <div class="size-14 rounded-2xl bg-primary-soft text-primary flex items-center justify-center mx-auto mb-4">
                        <x-icon.motorcycle class="w-7 h-7"/>
                    </div>
                    <p class="font-heading font-semibold text-foreground mb-1">Belum ada motor</p>
                    <p class="text-sm text-muted-fg mb-5">Tambahkan satu untuk mulai.</p>
                    <x-ui.button variant="primary" href="{{ route('motorcycles.create') }}">Tambah Motor</x-ui.button>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
