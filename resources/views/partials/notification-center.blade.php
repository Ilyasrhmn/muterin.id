@php $unreadCount = $navbarNotifications->whereNull('read_at')->count(); @endphp
<div x-data="{ open: false }" class="relative" @keydown.escape.stop="open = false; $refs.bell.focus()">
    <button type="button" x-ref="bell" @click="open = !open"
            :aria-expanded="open" aria-controls="notification-panel"
            aria-label="Notifikasi{{ $unreadCount ? ', '.$unreadCount.' belum dibaca' : '' }}"
            class="relative flex items-center justify-center min-h-11 min-w-11 rounded-token text-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-primary">
        <x-icon.bell class="w-5 h-5"/>
        @if ($unreadCount)
            <span class="absolute top-0 right-0 min-w-5 h-5 px-1 rounded-full bg-primary text-white text-[10px] font-bold flex items-center justify-center" aria-hidden="true">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </button>
    <section id="notification-panel" aria-labelledby="notification-title" x-show="open" x-cloak
             @click.outside="open = false" class="mtn-notification-panel">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-border">
            <div><h2 id="notification-title" class="font-bold">Notifikasi</h2><p class="text-xs text-muted-fg">{{ $unreadCount }} belum dibaca</p></div>
            <button type="button" @click="open = false; $refs.bell.focus()" class="mtn-icon-action" aria-label="Tutup notifikasi"><x-icon.x class="w-4 h-4"/></button>
        </div>
        @if ($unreadCount)
            <form method="POST" action="{{ route('notifications.read-all') }}" class="px-5 border-b border-border">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 min-h-11 text-sm font-semibold text-primary"><x-icon.check class="w-4 h-4"/>Tandai semua dibaca</button>
            </form>
        @endif
        <div class="max-h-[60dvh] overflow-y-auto">
            @forelse ($navbarNotifications as $notification)
                <form method="POST" action="{{ route('notifications.read', $notification) }}">
                    @csrf
                    <button type="submit" class="mtn-notification-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                        <x-icon.alert-triangle class="w-5 h-5 shrink-0 {{ $notification->read_at ? 'text-muted-fg' : ($notification->severity === 'red' ? 'text-red-700' : 'text-amber-800') }}"/>
                        <span class="min-w-0 flex-1"><span class="block text-sm">{{ $notification->message }}</span><span class="block text-xs font-normal text-muted-fg mt-1">{{ $notification->read_at ? 'Sudah dibaca' : 'Belum dibaca' }} · {{ $notification->created_at->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M, H:i') }}</span></span>
                        @unless ($notification->read_at)<span class="size-2 rounded-full bg-primary shrink-0 mt-1" aria-hidden="true"></span>@endunless
                    </button>
                </form>
            @empty
                <div class="px-5 py-8 text-center"><x-icon.bell class="w-7 h-7 mx-auto mb-3 text-muted-fg"/><p class="font-semibold">Belum ada notifikasi</p><p class="mt-1 text-sm text-muted-fg">Pengingat kendaraanmu akan muncul di sini.</p></div>
            @endforelse
        </div>
    </section>
</div>
