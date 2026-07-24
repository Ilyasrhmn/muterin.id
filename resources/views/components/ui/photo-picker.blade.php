@props(['id', 'name' => 'photo', 'label' => 'Foto (opsional)'])

<div class="space-y-1.5" data-photo-picker>
    <span class="block text-xs font-semibold text-muted-fg">{{ $label }}</span>
    <input id="{{ $id }}" name="{{ $name }}" type="file" accept="image/*" class="hidden" data-photo-picker-input>
    <div class="flex gap-2">
        <button type="button" data-photo-picker-camera
                class="flex-1 text-xs font-semibold rounded-lg border border-border bg-muted px-3 py-2 hover:bg-muted/70 transition">
            Ambil Foto
        </button>
        <button type="button" data-photo-picker-gallery
                class="flex-1 text-xs font-semibold rounded-lg border border-border bg-muted px-3 py-2 hover:bg-muted/70 transition">
            Pilih dari Galeri
        </button>
    </div>
    <p class="text-[11px] text-muted-fg truncate" data-photo-picker-filename></p>
</div>
