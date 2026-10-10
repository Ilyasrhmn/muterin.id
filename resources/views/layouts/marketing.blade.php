<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Buku servis digital Muterin: catat odometer, jadwal perawatan, pengeluaran, dan perjalanan motor dalam satu tempat.">
    <title>Muterin: Jalan terus. Rawat tepat waktunya.</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
</head>
<body class="lp-page">
    <a class="lp-skip" href="#lp-main">Lewati navigasi</a>
    <header class="lp-header" x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false">
        <nav class="lp-wrap lp-nav" aria-label="Navigasi utama">
            <a class="lp-brand" href="{{ route('home') }}"><img src="{{ asset('images/muterin-mark.svg') }}" alt="" width="32" height="32">Muterin<span class="lp-brand-note">Buku servis digital</span></a>
            <div class="lp-nav-links"><a href="{{ url('/').'#fitur' }}">Fitur</a><a href="{{ url('/').'#cara-kerja' }}">Cara Kerja</a><a href="{{ url('/').'#harga' }}">Harga</a><a href="{{ url('/').'#faq' }}">FAQ</a></div>
            <div class="lp-nav-account"><a class="lp-text-link" href="{{ route('login') }}">Masuk</a><a class="lp-button lp-button-small" href="{{ route('subscription') }}">Lihat paket</a></div>
            <button class="lp-menu-toggle" type="button" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-expanded="false" aria-controls="lp-mobile-nav" aria-label="Buka menu"><span x-text="mobileOpen ? 'Tutup' : 'Menu'">Menu</span></button>
        </nav>
        <nav id="lp-mobile-nav" class="lp-mobile-nav lp-wrap" aria-label="Navigasi seluler" x-show="mobileOpen" x-cloak><a href="{{ url('/').'#fitur' }}">Fitur</a><a href="{{ url('/').'#cara-kerja' }}">Cara Kerja</a><a href="{{ url('/').'#harga' }}">Harga</a><a href="{{ url('/').'#faq' }}">FAQ</a><a href="{{ route('login') }}">Masuk</a><a href="{{ route('subscription') }}">Lihat paket</a></nav>
    </header>
    <main id="lp-main" tabindex="-1">{{ $slot }}</main>
    @include('partials.subscription-dialog')
    <footer class="lp-footer"><div class="lp-wrap lp-footer-inner"><div><a class="lp-brand" href="{{ route('home') }}">Muterin</a><p>Buku servis untuk setiap perjalanan.</p><p class="lp-note">&copy; {{ date('Y') }} Muterin.</p></div><nav aria-label="Navigasi footer"><p class="lp-kicker">Navigasi</p><a href="{{ url('/').'#fitur' }}">Fitur</a><a href="{{ url('/').'#cara-kerja' }}">Cara Kerja</a><a href="{{ url('/').'#harga' }}">Harga</a><a href="{{ url('/').'#faq' }}">FAQ</a></nav><nav aria-label="Akun"><p class="lp-kicker">Akun</p><a href="{{ route('login') }}">Masuk</a><a href="{{ route('subscription') }}">Lihat paket</a></nav></div></footer>
    <script src="{{ asset('js/toast.js') }}?v={{ filemtime(public_path('js/toast.js')) }}"></script>
    @include('partials.toast-flash')
    <script src="{{ asset('js/pwa.js') }}?v={{ filemtime(public_path('js/pwa.js')) }}"></script>
</body>
</html>
