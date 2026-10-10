@props(['items'])
@if ($items->hasPages())
    <nav class="mtn-pagination" aria-label="Navigasi halaman data">
        <span>{{ $items->firstItem() }}–{{ $items->lastItem() }} dari {{ $items->total() }}</span>
        <div>
            @if ($items->onFirstPage())<span class="mtn-page-control" aria-disabled="true">Sebelumnya</span>
            @else<a class="mtn-page-control" href="{{ $items->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
            <span>{{ $items->currentPage() }} / {{ $items->lastPage() }}</span>
            @if ($items->hasMorePages())<a class="mtn-page-control" href="{{ $items->nextPageUrl() }}" rel="next">Berikutnya</a>
            @else<span class="mtn-page-control" aria-disabled="true">Berikutnya</span>@endif
        </div>
    </nav>
@endif
