<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Dashboard</h2></x-slot>
    <div class="max-w-4xl mx-auto p-4 space-y-6">
        @forelse ($dashboard as $row)
            @php $needsAttention = $row['items']->contains(fn ($i) => $i['status']['color'] !== 'green'); @endphp
            <div class="border rounded p-4 space-y-3">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold">{{ $row['motor']->nickname }}</h3>
                    @if ($needsAttention)
                        <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded">Perlu perhatian</span>
                    @endif
                </div>
                @foreach ($row['items'] as $i)
                    <x-status-bar :item="$i['item']" :status="$i['status']" />
                @endforeach
                <a href="{{ route('motorcycles.show', $row['motor']) }}" class="text-blue-600 text-sm">Detail & tandai servis &rarr;</a>
            </div>
        @empty
            <p>Belum ada motor. <a href="{{ route('motorcycles.create') }}" class="text-blue-600">Tambah motor</a>.</p>
        @endforelse
    </div>
    <script src="{{ asset('js/notify.js') }}"></script>
</x-app-layout>
