<x-app-layout>
    <x-slot name="header">Riwayat</x-slot>

    <div class="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        <x-ui.hero badge="Riwayat Aktivitas" title="Riwayat Perjalanan & Perawatan"
                    subtitle="Semua jejak riding dan servis motor kamu, tercatat rapi di satu tempat.">
            <x-slot:side>
                <x-ui.button variant="white" href="{{ route('history.export') }}">Export PDF</x-ui.button>
            </x-slot:side>
        </x-ui.hero>

        <div class="grid sm:grid-cols-3 gap-4">
            <div class="bg-primary-soft border border-primary/15 rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-white text-primary flex items-center justify-center mb-4">
                    <x-icon.wallet class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Biaya Perawatan</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $totalCost }}">0</span></p>
            </div>
            <div class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                    <x-icon.route class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Perjalanan</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight"><span data-countup="{{ $trips->count() }}">0</span></p>
            </div>
            <div class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4">
                    <x-icon.wrench class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Servis</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight"><span data-countup="{{ $logs->count() }}">0</span></p>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-surface border border-border rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm flex items-center gap-2">
                        <x-icon.route class="w-4 h-4 text-primary"/> Perjalanan
                    </h3>
                </div>
                <div data-reveal-group class="p-3 space-y-1 max-h-96 overflow-y-auto">
                    @forelse ($trips as $t)
                        <div data-reveal class="p-3 rounded-xl hover:bg-muted/60 transition">
                            <div class="flex justify-between items-center">
                                <p class="font-bold text-sm text-foreground">{{ $t->motorcycle->nickname }}</p>
                                <span class="text-sm font-bold text-primary tabular-nums">{{ $t->distance_km }} km</span>
                            </div>
                            <p class="text-[11px] text-muted-fg mt-0.5 tabular-nums">{{ $t->ended_at?->format('d M Y H:i') }} &middot; {{ gmdate('H:i:s', $t->duration_seconds) }}</p>
                        </div>
                    @empty
                        <p class="text-muted-fg text-sm p-3">Belum ada perjalanan.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-surface border border-border rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm flex items-center gap-2">
                        <x-icon.wrench class="w-4 h-4 text-primary"/> Perawatan
                    </h3>
                </div>
                <div data-reveal-group class="p-3 space-y-1 max-h-96 overflow-y-auto">
                    @forelse ($logs as $l)
                        <div data-reveal class="p-3 rounded-xl hover:bg-muted/60 transition">
                            <div class="flex justify-between items-center">
                                <p class="font-bold text-sm text-foreground">{{ $l->item->name }}</p>
                                <span class="text-sm font-bold text-foreground tabular-nums">Rp{{ number_format($l->cost) }}</span>
                            </div>
                            <p class="text-[11px] text-muted-fg mt-0.5 tabular-nums">{{ $l->item->motorcycle->nickname }} &middot; {{ $l->serviced_at->format('d M Y') }} &middot; {{ number_format($l->serviced_at_odometer_km) }} km</p>
                        </div>
                    @empty
                        <p class="text-muted-fg text-sm p-3">Belum ada perawatan.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
