<x-app-layout>
    <x-slot name="header">Edit Motor</x-slot>
    <div class="max-w-lg mx-auto p-4 sm:p-6 lg:p-8">
        <x-ui.card>
            <form method="POST" action="{{ route('motorcycles.update', $motorcycle) }}" class="space-y-5">
                @csrf
                @method('PUT')
                <x-ui.input name="nickname" label="Nama/Nickname" :value="old('nickname', $motorcycle->nickname)" required />
                <x-ui.input name="plat_nomor" label="Plat Nomor" :value="old('plat_nomor', $motorcycle->plat_nomor)" required />
                <x-ui.input name="brand" label="Merk" :value="old('brand', $motorcycle->brand)" />
                <x-ui.input name="model" label="Tipe" :value="old('model', $motorcycle->model)" />
                <x-ui.input name="year" label="Tahun" type="number" :value="old('year', $motorcycle->year)" />
                <x-ui.input name="initial_odometer_km" label="Odometer awal (km)" type="number" :value="old('initial_odometer_km', $motorcycle->initial_odometer_km)" required />

                <div x-data="{ open: true }" class="border-t border-border pt-4">
                    <button type="button" @click="open = !open" class="text-sm text-primary font-medium hover:underline">
                        Dokumen Kendaraan (opsional)
                    </button>
                    <div x-show="open" x-cloak class="grid sm:grid-cols-3 gap-4 mt-3">
                        <x-ui.input name="stnk_due_date" label="Jatuh Tempo Pajak STNK" type="date" :value="old('stnk_due_date', $motorcycle->stnk_due_date?->toDateString())" />
                        <x-ui.input name="plat_due_date" label="Jatuh Tempo Ganti Plat (5th)" type="date" :value="old('plat_due_date', $motorcycle->plat_due_date?->toDateString())" />
                        <x-ui.input name="insurance_due_date" label="Jatuh Tempo Asuransi" type="date" :value="old('insurance_due_date', $motorcycle->insurance_due_date?->toDateString())" />
                    </div>
                </div>

                <x-ui.button variant="primary" type="submit" class="w-full">Simpan Perubahan</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
