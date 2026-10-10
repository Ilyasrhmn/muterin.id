<x-app-layout>
    <x-slot name="header">Motor Saya</x-slot>

    <div class="mtn-page mtn-records-page space-y-6">

        <x-ui.hero badge="{{ $motorcycles->count() }} motor terdaftar" title="Motor Saya"
                    subtitle="Kelola semua motormu di sini dan pilih mana yang sedang kamu pakai riding.">
            <x-slot:side>
                <x-ui.button variant="white" href="{{ route('motorcycles.create') }}">Tambah Motor</x-ui.button>
            </x-slot:side>
        </x-ui.hero>

        <div data-reveal-group class="mtn-panel mtn-motor-list">
            @forelse ($motorcycles as $motor)
                <div data-reveal class="mtn-record mtn-motor-record {{ $motor->is_active ? 'mtn-active-record' : '' }}">
                    <div class="mtn-motor-identity">
                        <div class="flex items-start justify-between mb-3">
                            <div class="size-11 rounded-md bg-primary-soft text-primary flex items-center justify-center">
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
                        </div>
                    <div class="mtn-motor-odometer"><p class="text-xs text-muted-fg mb-2">Odometer saat ini</p>
                        <p class="text-xl font-bold flex items-center gap-1.5 tabular-nums">
                            <x-icon.gauge class="w-4 h-4"/> {{ number_format($motor->current_odometer_km) }} km
                        </p>
                    </div>
                    <div class="mtn-record-actions">
                        <x-ui.button variant="outline" size="sm" href="{{ route('motorcycles.show', $motor) }}">Detail & Perawatan</x-ui.button>
                        @unless ($motor->is_active)
                            <form method="POST" action="{{ route('motorcycles.activate', $motor) }}">
                                @csrf
                                <x-ui.button variant="outline" size="sm" type="submit" class="w-full">Jadikan Aktif</x-ui.button>
                            </form>
                        @endunless
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-surface border border-border rounded-lg text-center py-14">
                    <div class="size-14 rounded-lg bg-primary-soft text-primary flex items-center justify-center mx-auto mb-4">
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
