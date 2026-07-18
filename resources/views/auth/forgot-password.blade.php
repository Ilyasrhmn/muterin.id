<x-guest-layout>
    <h1 class="text-2xl font-heading font-bold text-foreground mb-1">Lupa password?</h1>
    <p class="text-sm text-muted-fg mb-6">
        Gak masalah. Masukkan email-mu, kami kirim link buat bikin password baru.
    </p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-ui.input name="email" label="Email" type="email" :value="old('email')" required autofocus />

        <x-ui.button variant="primary" type="submit" class="w-full">Kirim Link Reset Password</x-ui.button>
    </form>
</x-guest-layout>
