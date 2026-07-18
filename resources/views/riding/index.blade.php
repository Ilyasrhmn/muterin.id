<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Riding</h2></x-slot>
    <div class="max-w-lg mx-auto p-4 space-y-4" id="riding-app">
        @if ($motorcycles->isEmpty())
            <p>Belum ada motor. <a href="{{ route('motorcycles.create') }}" class="text-blue-600">Tambah motor</a> dulu sebelum mulai riding.</p>
        @else
            <label class="block">Pilih motor
                <select id="motor-select" class="w-full border rounded p-2">
                    @foreach ($motorcycles as $m)
                        <option value="{{ $m->id }}" @selected($m->is_active)>{{ $m->nickname }} ({{ number_format($m->current_odometer_km) }} km)</option>
                    @endforeach
                </select>
            </label>
            <div class="text-center py-6 border rounded">
                <p class="text-4xl font-bold"><span id="distance">0.00</span> km</p>
                <p class="text-gray-500"><span id="duration">00:00</span></p>
            </div>
            <button id="start-btn" class="w-full py-3 bg-green-600 text-white rounded text-lg">Mulai Perjalanan</button>
            <button id="stop-btn" class="w-full py-3 bg-red-600 text-white rounded text-lg hidden">Selesai Perjalanan</button>
            <p id="gps-msg" class="text-sm text-red-600"></p>
        @endif
    </div>
    @csrf
    @if ($motorcycles->isNotEmpty())
        <script src="{{ asset('js/trip-recorder.js') }}"></script>
    @endif
</x-app-layout>
