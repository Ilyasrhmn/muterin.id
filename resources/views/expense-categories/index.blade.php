<x-app-layout>
    <x-slot name="header">Kategori Biaya</x-slot>
    <div class="mtn-page" x-data="{ search: '' }">
        <x-ui.hero title="Kategori Biaya" subtitle="Atur kategori pengeluaran lain. Mengubah nama kategori tidak mengubah catatan lama.">
            <x-slot:side><x-ui.button type="button" @click="$dispatch('open-modal', 'add-category')"><x-icon.plus class="w-5 h-5"/>Tambah kategori</x-ui.button></x-slot:side>
        </x-ui.hero>
        <div class="space-y-6">
            <section class="mtn-panel" aria-labelledby="category-title">
                <h2 id="category-title" class="mtn-section-title">Kategori saya</h2>
                <label class="block mt-4 mb-3">
                    <span class="block text-sm font-semibold mb-2">Cari kategori</span>
                    <input type="search" x-model="search" placeholder="Cari nama kategori" class="mtn-input w-full">
                </label>
                @forelse ($categories as $category)
                    <div class="mtn-category-row" x-show="@js(mb_strtolower($category->name)).includes(search.toLowerCase())"
                         x-data="{ editing: false, original: @js($category->name) }">
                        <x-icon.tag class="w-5 h-5 text-muted-fg shrink-0"/>
                        <form method="POST" action="{{ route('expense-categories.update', $category) }}" class="mtn-category-edit">
                            @csrf @method('PATCH')
                            <span x-show="!editing" class="font-medium break-words">{{ $category->name }}</span>
                            <label x-show="editing" x-cloak class="min-w-0 flex-1">
                                <span class="sr-only">Nama kategori {{ $category->name }}</span>
                                <input name="name" value="{{ $category->name }}" maxlength="50" required x-ref="input" :disabled="!editing" class="mtn-input w-full">
                            </label>
                            <button type="button" x-show="!editing" @click="editing = true; $nextTick(() => $refs.input.focus())" class="mtn-icon-action" aria-label="Edit kategori {{ $category->name }}">
                                <x-icon.pencil class="w-4 h-4"/><span class="hidden sm:inline">Edit</span>
                            </button>
                            <button type="submit" x-show="editing" x-cloak class="mtn-icon-action text-primary" aria-label="Simpan kategori {{ $category->name }}"><x-icon.check class="w-4 h-4"/></button>
                            <button type="button" x-show="editing" x-cloak @click="editing = false; $refs.input.value = original" class="mtn-icon-action" aria-label="Batalkan edit kategori {{ $category->name }}"><x-icon.x class="w-4 h-4"/></button>
                        </form>
                        <form method="POST" action="{{ route('expense-categories.destroy', $category) }}" onsubmit="return confirm('Hapus kategori ini? Catatan lama tidak terpengaruh.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="mtn-icon-action text-accent" aria-label="Hapus kategori {{ $category->name }}"><x-icon.trash class="w-4 h-4"/><span class="hidden sm:inline">Hapus</span></button>
                        </form>
                    </div>
                @empty
                    <p class="py-8 text-sm text-muted-fg">Belum ada kategori. Gunakan tombol Tambah kategori untuk membuat kategori pertama.</p>
                @endforelse
                @if ($categories->isNotEmpty())
                    <p x-show="search && !@js($categories->pluck('name')->map(fn ($name) => mb_strtolower($name))->values()).some(name => name.includes(search.toLowerCase()))" x-cloak class="py-6 text-sm text-muted-fg">Tidak ada kategori yang cocok.</p>
                @endif
            </section>
            <x-modal name="add-category" :show="$errors->has('name') && old('_form') === 'add-category'" maxWidth="lg" labelledby="add-category-title" focusable>
            <div class="p-6">
                <div class="flex items-center justify-between gap-4"><h2 id="add-category-title" class="mtn-section-title">Tambah kategori</h2><button type="button" @click="$dispatch('close')" class="mtn-icon-action" aria-label="Tutup formulir kategori"><x-icon.x class="w-5 h-5"/></button></div>

                <form method="POST" action="{{ route('expense-categories.store') }}" class="space-y-4 mt-4">
                    @csrf
                    <input type="hidden" name="_form" value="add-category">
                    <label class="block"><span class="block text-sm font-semibold mb-2">Nama kategori</span><input name="name" value="{{ old('name') }}" required maxlength="50" placeholder="Contoh: Modifikasi" class="mtn-input w-full" @error('name') aria-invalid="true" aria-describedby="category-name-error" @enderror></label>
                    @error('name')<p id="category-name-error" class="text-sm text-accent">{{ $message }}</p>@enderror
                    <p class="text-sm text-muted-fg">Kategori ini menjadi pilihan saat mencatat Pengeluaran Lain.</p>
                    <div class="flex justify-end gap-3"><x-ui.button variant="outline" type="button" @click="$dispatch('close')">Batal</x-ui.button><x-ui.button type="submit"><x-icon.check class="w-4 h-4"/>Simpan kategori</x-ui.button></div>
                </form>
            </div>
            </x-modal>
        </div>
    </div>
</x-app-layout>
