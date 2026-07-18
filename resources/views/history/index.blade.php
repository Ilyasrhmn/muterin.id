<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Riwayat</h2></x-slot>
    <div class="max-w-4xl mx-auto p-4 grid md:grid-cols-2 gap-6">
        <div>
            <h3 class="font-bold mb-2">Perjalanan</h3>
            @forelse ($trips as $t)
                <div class="border rounded p-3 mb-2 text-sm">
                    <div class="font-medium">{{ $t->motorcycle->nickname }} &mdash; {{ $t->distance_km }} km</div>
                    <div class="text-gray-500">{{ $t->ended_at?->format('d M Y H:i') }} &middot; {{ gmdate('H:i:s', $t->duration_seconds) }}</div>
                </div>
            @empty
                <p class="text-gray-500">Belum ada perjalanan.</p>
            @endforelse
        </div>
        <div>
            <h3 class="font-bold mb-2">Perawatan <span class="text-sm text-gray-500">(total Rp{{ number_format($totalCost) }})</span></h3>
            @forelse ($logs as $l)
                <div class="border rounded p-3 mb-2 text-sm">
                    <div class="font-medium">{{ $l->item->name }} &mdash; {{ $l->item->motorcycle->nickname }}</div>
                    <div class="text-gray-500">{{ $l->serviced_at->format('d M Y') }} &middot; {{ number_format($l->serviced_at_odometer_km) }} km &middot; Rp{{ number_format($l->cost) }}</div>
                </div>
            @empty
                <p class="text-gray-500">Belum ada perawatan.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
