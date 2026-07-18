<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-xl">Motor Saya</h2>
            <x-ui.button variant="primary" size="sm" href="{{ route('motorcycles.create') }}">
                <x-icon.plus class="w-4 h-4"/> Tambah Motor
            </x-ui.button>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto p-4 md:p-6 space-y-4">
        @if (session('status'))
            <div class="p-3 rounded-token bg-status-green/10 text-status-green text-sm">{{ session('status') }}</div>
        @endif

        <div data-reveal-group class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($motorcycles as $motor)
                <x-ui.card hover data-reveal class="{{ $motor->is_active ? 'ring-2 ring-primary' : '' }}">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <a href="{{ route('motorcycles.show', $motor) }}" class="font-heading font-semibold text-foreground hover:text-primary">
                                {{ $motor->nickname }}
                            </a>
                            <p class="text-sm text-muted-fg">{{ $motor->brand }} {{ $motor->model }}</p>
                        </div>
                        @if ($motor->is_active)
                            <x-ui.badge variant="green">Aktif</x-ui.badge>
                        @endif
                    </div>
                    <p class="text-sm text-muted-fg flex items-center gap-1.5 mb-4">
                        <x-icon.gauge class="w-4 h-4"/> {{ number_format($motor->current_odometer_km) }} km
                    </p>
                    @unless ($motor->is_active)
                        <form method="POST" action="{{ route('motorcycles.activate', $motor) }}">
                            @csrf
                            <x-ui.button variant="outline" size="sm" type="submit" class="w-full">Jadikan Aktif</x-ui.button>
                        </form>
                    @endunless
                </x-ui.card>
            @empty
                <x-ui.card class="col-span-full text-center py-12">
                    <x-icon.motorcycle class="w-10 h-10 text-muted-fg mx-auto mb-3"/>
                    <p class="text-muted-fg mb-4">Belum ada motor. Tambahkan satu.</p>
                    <x-ui.button variant="primary" href="{{ route('motorcycles.create') }}">
                        <x-icon.plus class="w-4 h-4"/> Tambah Motor
                    </x-ui.button>
                </x-ui.card>
            @endforelse
        </div>
    </div>
</x-app-layout>
