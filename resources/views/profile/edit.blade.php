<x-app-layout>
    <x-slot name="header">Pengaturan</x-slot>
    <div class="mtn-page">
        <x-ui.hero title="Pengaturan akun" subtitle="Kelola informasi profil, keamanan, dan akun Muterin." />
        <div class="mtn-settings-grid">
            <div class="mtn-panel">@include('profile.partials.update-profile-information-form')</div>
            <div class="mtn-panel">@include('profile.partials.update-password-form')</div>
        </div>
        <div class="mtn-panel mtn-danger-panel">@include('profile.partials.delete-user-form')</div>
    </div>
</x-app-layout>
