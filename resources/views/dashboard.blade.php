<x-app-layout>
    <x-slot name="header"><h2 class="text-xl">Dashboard</h2></x-slot>

    <div class="max-w-6xl mx-auto p-4 md:p-6 space-y-8">
        <div data-reveal-group class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-ui.stat-tile label="Total Motor" :value="$kpi['motor_count']" data-reveal>
                <x-slot:icon><x-icon.motorcycle class="w-6 h-6"/></x-slot:icon>
            </x-ui.stat-tile>
            <x-ui.stat-tile label="Total KM Ditempuh" :value="$kpi['total_km']" suffix=" km" data-reveal>
                <x-slot:icon><x-icon.gauge class="w-6 h-6"/></x-slot:icon>
            </x-ui.stat-tile>
            <x-ui.stat-tile label="Perlu Perhatian" :value="$kpi['attention']" data-reveal>
                <x-slot:icon><x-icon.bell class="w-6 h-6"/></x-slot:icon>
            </x-ui.stat-tile>
            <x-ui.stat-tile label="Total Biaya Servis" :value="$kpi['total_cost']" suffix=" Rp" data-reveal>
                <x-slot:icon><x-icon.wallet class="w-6 h-6"/></x-slot:icon>
            </x-ui.stat-tile>
        </div>

        <div data-reveal-group class="space-y-5">
            @forelse ($dashboard as $row)
                @php $needsAttention = $row['items']->contains(fn ($i) => $i['status']['color'] !== 'green'); @endphp
                <x-ui.card hover data-reveal class="space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="font-heading font-semibold text-lg flex items-center gap-2">
                            <x-icon.motorcycle class="w-5 h-5 text-primary"/> {{ $row['motor']->nickname }}
                        </h3>
                        @if ($needsAttention)
                            <x-ui.badge variant="red">Perlu perhatian</x-ui.badge>
                        @else
                            <x-ui.badge variant="green">Aman</x-ui.badge>
                        @endif
                    </div>

                    <div class="grid sm:grid-cols-2 gap-x-6 gap-y-3">
                        @foreach ($row['items'] as $i)
                            <div data-item-id="{{ $i['item']->id }}" data-item-name="{{ $i['item']->name }}" data-color="{{ $i['status']['color'] }}" class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-foreground">{{ $i['item']->name }}</span>
                                    <span class="text-muted-fg">{{ $i['status']['used'] }} / {{ $i['item']->interval_km }} km</span>
                                </div>
                                <x-ui.progress :percent="$i['status']['percent']" :color="$i['status']['color']" />
                            </div>
                        @endforeach
                    </div>

                    <a href="{{ route('motorcycles.show', $row['motor']) }}" class="inline-flex items-center gap-1 text-sm text-primary font-medium hover:underline">
                        Detail & tandai servis <span aria-hidden="true">&rarr;</span>
                    </a>
                </x-ui.card>
            @empty
                <x-ui.card class="text-center py-12">
                    <x-icon.motorcycle class="w-10 h-10 text-muted-fg mx-auto mb-3"/>
                    <p class="text-muted-fg mb-4">Belum ada motor terdaftar.</p>
                    <x-ui.button variant="primary" href="{{ route('motorcycles.create') }}">
                        <x-icon.plus class="w-4 h-4"/> Tambah Motor
                    </x-ui.button>
                </x-ui.card>
            @endforelse
        </div>
    </div>

    <script src="{{ asset('js/notify.js') }}"></script>
</x-app-layout>
