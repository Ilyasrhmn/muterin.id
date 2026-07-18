<x-guest-layout>
    <h1 class="text-2xl font-heading font-bold text-foreground mb-1">Buat akun</h1>
    <p class="text-sm text-muted-fg mb-6">Gratis, mulai rawat motormu sekarang.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <x-ui.input name="name" label="Nama" type="text" :value="old('name')" required autofocus autocomplete="name" />

        <x-ui.input name="email" label="Email" type="email" :value="old('email')" required autocomplete="username" />

        <x-ui.input name="password" label="Password" type="password" required autocomplete="new-password" />

        <x-ui.input name="password_confirmation" label="Konfirmasi Password" type="password" required autocomplete="new-password" />

        <x-ui.button variant="primary" type="submit" class="w-full">Daftar</x-ui.button>

        <p class="text-center text-sm text-muted-fg">
            Sudah punya akun? <a href="{{ route('login') }}" class="text-primary font-medium hover:underline">Masuk</a>
        </p>
    </form>
</x-guest-layout>
