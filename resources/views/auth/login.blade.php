<x-guest-layout>
    <h1 class="text-2xl font-heading font-bold text-foreground mb-1">Masuk ke akunmu</h1>
    <p class="text-sm text-muted-fg mb-6">Pantau perawatan motormu lagi.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-ui.input name="email" label="Email" type="email" :value="old('email')" required autofocus autocomplete="username" />

        <x-ui.input name="password" label="Password" type="password" required autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-muted-fg">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-border text-primary focus:ring-primary/30">
                Ingat saya
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-primary hover:underline" href="{{ route('password.request') }}">
                    Lupa password?
                </a>
            @endif
        </div>

        <x-ui.button variant="primary" type="submit" class="w-full">Masuk</x-ui.button>

        <p class="text-center text-sm text-muted-fg">
            Belum punya akun? <a href="{{ route('register') }}" class="text-primary font-medium hover:underline">Daftar</a>
        </p>
    </form>
</x-guest-layout>
