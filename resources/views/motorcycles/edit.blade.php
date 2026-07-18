<x-app-layout>
    <x-slot name="header"><h2 class="text-xl">Edit Motor</h2></x-slot>
    <div class="max-w-lg mx-auto p-4 md:p-6">
        <x-ui.card>
            <form method="POST" action="{{ route('motorcycles.update', $motorcycle) }}" class="space-y-5">
                @csrf
                @method('PUT')
                <x-ui.input name="nickname" label="Nama/Nickname" :value="old('nickname', $motorcycle->nickname)" required />
                <x-ui.input name="brand" label="Merk" :value="old('brand', $motorcycle->brand)" />
                <x-ui.input name="model" label="Tipe" :value="old('model', $motorcycle->model)" />
                <x-ui.input name="year" label="Tahun" type="number" :value="old('year', $motorcycle->year)" />
                <x-ui.input name="initial_odometer_km" label="Odometer awal (km)" type="number" :value="old('initial_odometer_km', $motorcycle->initial_odometer_km)" required />
                <x-ui.button variant="primary" type="submit" class="w-full">Simpan Perubahan</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
