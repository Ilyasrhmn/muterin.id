<x-app-layout>
    <x-slot name="header">Tambah Motor</x-slot>
    <div class="max-w-lg mx-auto p-4 sm:p-6 lg:p-8">
        <x-ui.card>
            <form method="POST" action="{{ route('motorcycles.store') }}" class="space-y-5">
                @csrf
                <x-ui.input name="nickname" label="Nama/Nickname" :value="old('nickname')" placeholder="Beat Merah" required />
                <x-ui.input name="plat_nomor" label="Plat Nomor" :value="old('plat_nomor')" placeholder="B 3421 XYZ" required />
                <x-ui.input name="brand" label="Merk" :value="old('brand')" placeholder="Honda" />
                <x-ui.input name="model" label="Tipe" :value="old('model')" placeholder="Beat" />
                <x-ui.input name="year" label="Tahun" type="number" :value="old('year')" placeholder="2022" />
                <x-ui.input name="initial_odometer_km" label="Odometer saat ini (km)" type="number" :value="old('initial_odometer_km', 0)" required />
                <x-ui.button variant="primary" type="submit" class="w-full">Simpan</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
