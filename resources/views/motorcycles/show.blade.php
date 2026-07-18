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
            @foreach ($motorcycle->maintenanceItems as $item)
                <div class="border rounded p-3">
                    <p class="font-medium">{{ $item->name }}</p>
                    <p class="text-sm text-gray-500">Interval: {{ number_format($item->interval_km) }} km &middot; Terakhir di: {{ number_format($item->last_service_odometer_km) }} km</p>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
