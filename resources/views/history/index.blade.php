<x-app-layout>
    <x-slot name="header"><h2 class="text-xl">Riwayat</h2></x-slot>

    <div class="max-w-5xl mx-auto p-4 md:p-6 space-y-6">
        <x-ui.stat-tile label="Total Biaya Perawatan" :value="$totalCost" suffix=" Rp" class="max-w-sm">
            <x-slot:icon><x-icon.wallet class="w-6 h-6"/></x-slot:icon>
        </x-ui.stat-tile>

        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-heading font-semibold mb-3 flex items-center gap-2">
                    <x-icon.route class="w-5 h-5 text-primary"/> Perjalanan
                </h3>
                <div data-reveal-group class="space-y-3">
                    @forelse ($trips as $t)
                        <div data-reveal class="relative pl-5 border-l-2 border-border">
                            <span class="absolute -left-[7px] top-1.5 w-3 h-3 rounded-full bg-primary"></span>
                            <x-ui.card class="py-3">
                                <p class="font-medium text-sm text-foreground">{{ $t->motorcycle->nickname }} &mdash; {{ $t->distance_km }} km</p>
                                <p class="text-xs text-muted-fg mt-0.5">{{ $t->ended_at?->format('d M Y H:i') }} &middot; {{ gmdate('H:i:s', $t->duration_seconds) }}</p>
                            </x-ui.card>
                        </div>
                    @empty
                        <p class="text-muted-fg text-sm">Belum ada perjalanan.</p>
                    @endforelse
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-heading font-semibold flex items-center gap-2">
                        <x-icon.wrench class="w-5 h-5 text-primary"/> Perawatan
                    </h3>
                    <x-ui.button variant="outline" size="sm" href="{{ route('history.export') }}">Export PDF</x-ui.button>
                </div>
                <div data-reveal-group class="space-y-3">
                    @forelse ($logs as $l)
                        <div data-reveal class="relative pl-5 border-l-2 border-border">
                            <span class="absolute -left-[7px] top-1.5 w-3 h-3 rounded-full bg-status-green"></span>
                            <x-ui.card class="py-3">
                                <p class="font-medium text-sm text-foreground">{{ $l->item->name }} &mdash; {{ $l->item->motorcycle->nickname }}</p>
                                <p class="text-xs text-muted-fg mt-0.5">{{ $l->serviced_at->format('d M Y') }} &middot; {{ number_format($l->serviced_at_odometer_km) }} km &middot; Rp{{ number_format($l->cost) }}</p>
                            </x-ui.card>
                        </div>
                    @empty
                        <p class="text-muted-fg text-sm">Belum ada perawatan.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
