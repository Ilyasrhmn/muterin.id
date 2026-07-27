<x-app-layout>
    <x-slot name="header">Kelola Kategori Biaya</x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-2xl mx-auto space-y-6">
        <div class="bg-surface border border-border rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-border bg-muted/40">
                <h3 class="font-heading font-bold text-foreground text-sm">Tambah Kategori</h3>
                <p class="text-xs text-muted-fg mt-0.5">Kategori ini jadi pilihan saat mencatat Pengeluaran Lain.</p>
            </div>
            <form method="POST" action="{{ route('expense-categories.store') }}" class="p-5 flex gap-3">
                @csrf
                <input name="name" required maxlength="50" placeholder="mis. Tol, Modifikasi, Denda"
                       class="flex-1 rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                <x-ui.button variant="primary" type="submit">Tambah</x-ui.button>
            </form>
            @error('name')<p class="px-5 pb-4 -mt-2 text-xs text-accent">{{ $message }}</p>@enderror
        </div>

        <div class="bg-surface border border-border rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-border bg-muted/40">
                <h3 class="font-heading font-bold text-foreground text-sm">Kategori Saya</h3>
            </div>
            <div class="divide-y divide-border">
                @forelse ($categories as $category)
                    <div class="flex items-center gap-3 p-4" x-data="{ editing: false }">
                        <form method="POST" action="{{ route('expense-categories.update', $category) }}" class="flex-1 flex gap-2">
                            @csrf @method('PATCH')
                            <input name="name" value="{{ $category->name }}" maxlength="50" required
                                   x-ref="input" :readonly="!editing" @click="if (!editing) { editing = true; $refs.input.focus(); }"
                                   :class="editing ? 'bg-surface focus:border-primary focus:ring-2 focus:ring-primary/20' : 'bg-muted/40 cursor-pointer'"
                                   class="flex-1 rounded-lg border border-border px-3 py-2 text-sm outline-none">
                            <button type="button" x-show="!editing" @click="editing = true; $refs.input.focus()"
                                    title="Edit" aria-label="Edit" class="p-2 rounded-lg text-primary hover:bg-primary-soft shrink-0">
                                <x-icon.pencil class="w-4 h-4"/>
                            </button>
                            <button type="submit" x-show="editing" x-cloak
                                    title="Simpan" aria-label="Simpan" class="p-2 rounded-lg text-primary hover:bg-primary-soft shrink-0">
                                <x-icon.check class="w-4 h-4"/>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('expense-categories.destroy', $category) }}"
                              onsubmit="return confirm('Hapus kategori ini? Catatan lama tidak terpengaruh.')">
                            @csrf @method('DELETE')
                            <button type="submit" title="Hapus" aria-label="Hapus" class="p-2 rounded-lg text-accent hover:bg-accent/10 shrink-0">
                                <x-icon.trash class="w-4 h-4"/>
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-muted-fg p-5">Belum ada kategori.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
