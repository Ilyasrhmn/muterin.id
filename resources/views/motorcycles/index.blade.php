<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Motor Saya</h2></x-slot>
    <div class="max-w-4xl mx-auto p-4 space-y-4">
        @if (session('status'))
            <div class="p-3 bg-green-100 rounded">{{ session('status') }}</div>
        @endif
        <a href="{{ route('motorcycles.create') }}" class="inline-block px-4 py-2 bg-blue-600 text-white rounded">+ Tambah Motor</a>
        <div class="grid gap-3">
            @forelse ($motorcycles as $motor)
                <div class="border rounded p-4 flex justify-between items-center {{ $motor->is_active ? 'ring-2 ring-blue-500' : '' }}">
                    <div>
                        <a href="{{ route('motorcycles.show', $motor) }}" class="font-bold">{{ $motor->nickname }}</a>
                        <p class="text-sm text-gray-500">{{ $motor->brand }} {{ $motor->model }} &mdash; {{ number_format($motor->current_odometer_km) }} km</p>
                    </div>
                    @unless ($motor->is_active)
                        <form method="POST" action="{{ route('motorcycles.activate', $motor) }}">
                            @csrf
                            <button class="px-3 py-1 text-sm bg-gray-200 rounded">Jadikan Aktif</button>
                        </form>
                    @else
                        <span class="text-blue-600 text-sm font-medium">Aktif</span>
                    @endunless
                </div>
            @empty
                <p class="text-gray-500">Belum ada motor. Tambahkan satu.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
