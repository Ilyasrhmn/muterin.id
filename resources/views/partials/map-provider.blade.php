@php
    $mapboxToken = config('services.mapbox.access_token');
    // Only public tokens may be included in browser-rendered maps.
    $mapboxToken = is_string($mapboxToken) && str_starts_with($mapboxToken, 'pk.') ? $mapboxToken : null;
@endphp
<script>
    window.MuterinMapConfig = {{ Illuminate\Support\Js::from([
        'accessToken' => $mapboxToken,
        'style' => config('services.mapbox.style'),
    ]) }};
</script>
