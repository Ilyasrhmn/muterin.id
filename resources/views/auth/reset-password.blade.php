<x-guest-layout>
    <h1 class="text-2xl font-heading font-bold text-foreground mb-6">Buat password baru</h1>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.input name="email" label="Email" type="email" :value="$request->email" required autofocus autocomplete="username" />

        <x-ui.input name="password" label="Password Baru" type="password" required autocomplete="new-password" />

        <x-ui.input name="password_confirmation" label="Konfirmasi Password" type="password" required autocomplete="new-password" />

        <x-ui.button variant="primary" type="submit" class="w-full">Reset Password</x-ui.button>
    </form>
</x-guest-layout>
