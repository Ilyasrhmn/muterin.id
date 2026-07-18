<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Tambah Motor</h2></x-slot>
    <div class="max-w-lg mx-auto p-4">
        <form method="POST" action="{{ route('motorcycles.store') }}" class="space-y-3">
            @csrf
            <div>
                <label>Nama/Nickname</label>
                <input name="nickname" value="{{ old('nickname') }}" class="w-full border rounded p-2" required>
            </div>
            <div>
                <label>Merk</label>
                <input name="brand" value="{{ old('brand') }}" class="w-full border rounded p-2">
            </div>
            <div>
                <label>Tipe</label>
                <input name="model" value="{{ old('model') }}" class="w-full border rounded p-2">
            </div>
            <div>
                <label>Tahun</label>
                <input type="number" name="year" value="{{ old('year') }}" class="w-full border rounded p-2">
            </div>
            <div>
                <label>Odometer saat ini (km)</label>
                <input type="number" name="initial_odometer_km" value="{{ old('initial_odometer_km', 0) }}" class="w-full border rounded p-2" required>
            </div>
            @error('nickname')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
            <button class="px-4 py-2 bg-blue-600 text-white rounded">Simpan</button>
        </form>
    </div>
</x-app-layout>
