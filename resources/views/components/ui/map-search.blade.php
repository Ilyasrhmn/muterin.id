@props(['inputId' => 'search-location', 'locate' => false])
<div class="mtn-map-search {{ $locate ? 'mtn-map-search-with-location' : '' }}">
    <form class="mtn-map-search-form" role="search" onsubmit="event.preventDefault(); document.getElementById('search-btn').click()">
        <div class="mtn-map-search-field">
            <x-icon.search class="w-5 h-5 shrink-0 text-muted-fg" aria-hidden="true"/>
            <input id="{{ $inputId }}" aria-label="Cari lokasi" type="search" placeholder="Cari lokasi…" autocomplete="off">
            <button id="search-btn" type="button">Cari</button>
        </div>
        <div id="search-results" class="hidden bg-surface border border-border rounded-lg shadow-lg"></div>
    </form>
    @if ($locate)
        <button id="btn-my-location" type="button" class="mtn-map-locate" aria-label="Gunakan lokasi saya sebagai titik awal" title="Gunakan lokasi saya">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/><path d="M12 1v4m0 14v4M1 12h4m14 0h4"/></svg>
        </button>
    @endif
</div>
