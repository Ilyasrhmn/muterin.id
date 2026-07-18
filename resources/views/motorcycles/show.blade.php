<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">{{ $motorcycle->nickname }}</h2></x-slot>
    <div class="max-w-2xl mx-auto p-4 space-y-4">
        @if (session('status'))
            <div class="p-3 bg-green-100 rounded">{{ session('status') }}</div>
        @endif
        <div class="flex justify-between items-center">
            <p class="text-gray-600">{{ $motorcycle->brand }} {{ $motorcycle->model }} &mdash; {{ number_format($motorcycle->current_odometer_km) }} km</p>
            <a href="{{ route('motorcycles.edit', $motorcycle) }}" class="text-sm text-blue-600">Edit</a>
        </div>
        <div class="space-y-4">
            @foreach ($items as $i)
                <div class="border rounded p-3 space-y-2" x-data="{ open: false }">
                    <x-status-bar :item="$i['item']" :status="$i['status']" />
                    @if ($i['status']['color'] === 'red')
                        <a href="https://www.google.com/maps/search/bengkel+motor+terdekat/" target="_blank" rel="noopener" class="inline-block text-sm text-red-600 underline">Cari Bengkel Terdekat &rarr;</a>
                    @endif
                    <button @click="open = !open" class="text-sm text-blue-600">Tandai "{{ $i['item']->name }}" selesai</button>
                    <form x-show="open" method="POST" action="{{ route('maintenance.complete', $i['item']) }}" class="mt-2 space-y-2">
                        @csrf
                        <input type="number" name="cost" placeholder="Biaya (opsional)" class="border rounded p-1 w-full">
                        <input type="date" name="serviced_at" value="{{ now()->toDateString() }}" class="border rounded p-1 w-full" required>
                        <button class="px-3 py-1 bg-green-600 text-white rounded text-sm">Simpan</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
