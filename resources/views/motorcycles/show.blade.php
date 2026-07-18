<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-xl">{{ $motorcycle->nickname }}</h2>
            <x-ui.button variant="outline" size="sm" href="{{ route('motorcycles.edit', $motorcycle) }}">Edit</x-ui.button>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto p-4 md:p-6 space-y-4">
        @if (session('status'))
            <div class="p-3 rounded-token bg-status-green/10 text-status-green text-sm">{{ session('status') }}</div>
        @endif

        <p class="text-muted-fg flex items-center gap-1.5">
            <x-icon.gauge class="w-4 h-4"/> {{ $motorcycle->brand }} {{ $motorcycle->model }} &mdash; {{ number_format($motorcycle->current_odometer_km) }} km
        </p>

        <div data-reveal-group class="space-y-4">
            @foreach ($items as $i)
                <x-ui.card data-reveal x-data="{ open: false }" class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="font-medium text-foreground">{{ $i['item']->name }}</span>
                        <span class="text-muted-fg">{{ $i['status']['used'] }} / {{ $i['item']->interval_km }} km ({{ $i['status']['percent'] }}%)</span>
                    </div>
                    <x-ui.progress :percent="$i['status']['percent']" :color="$i['status']['color']" />

                    @if ($i['status']['color'] === 'red')
                        <x-ui.button variant="accent" size="sm" href="https://www.google.com/maps/search/bengkel+motor+terdekat/" target="_blank" rel="noopener">
                            <x-icon.map-pin class="w-4 h-4"/> Cari Bengkel Terdekat
                        </x-ui.button>
                    @endif

                    <button @click="open = !open" type="button" class="text-sm text-primary font-medium hover:underline">
                        Tandai "{{ $i['item']->name }}" selesai
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('maintenance.complete', $i['item']) }}" class="space-y-3 pt-2 border-t border-border">
                        @csrf
                        <x-ui.input name="cost" label="Biaya (opsional)" type="number" placeholder="0" />
                        <x-ui.input name="serviced_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                        <x-ui.button variant="primary" size="sm" type="submit">
                            <x-icon.check class="w-4 h-4"/> Simpan
                        </x-ui.button>
                    </form>
                </x-ui.card>
            @endforeach
        </div>
    </div>
</x-app-layout>
