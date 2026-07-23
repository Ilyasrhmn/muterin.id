<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider as SocialiteProviderContract;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $email, string $name = 'Budi Santoso'): void
    {
        $googleUser = Mockery::mock(SocialiteUserContract::class);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);
        $googleUser->shouldReceive('getNickname')->andReturn('budi');

        // Mock the interface, not the concrete GoogleProvider — Mockery can't
        // safely mock a concrete class if it (or a parent) is marked final.
        $provider = Mockery::mock(SocialiteProviderContract::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_login_page_has_google_button(): void
    {
        $this->get('/login')->assertSee(route('auth.google.redirect'), false);
    }

    public function test_register_page_has_google_button(): void
    {
        $this->get('/register')->assertSee(route('auth.google.redirect'), false);
    }

    public function test_new_email_creates_account_and_logs_in(): void
    {
        $this->fakeGoogleUser('orangbaru@gmail.com', 'Orang Baru');

        $response = $this->get('/auth/google/callback');

        $this->assertDatabaseHas('users', ['email' => 'orangbaru@gmail.com', 'name' => 'Orang Baru']);
        $user = User::where('email', 'orangbaru@gmail.com')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_existing_manual_account_auto_links_by_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'sudahdaftar@gmail.com',
            'password' => Hash::make('password-manual-lama'),
        ]);
        $this->fakeGoogleUser('sudahdaftar@gmail.com', 'Nama Dari Google');

        $countBefore = User::count();
        $response = $this->get('/auth/google/callback');

        $this->assertSame($countBefore, User::count()); // tidak bikin user baru
        $this->assertAuthenticatedAs($existing);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_existing_google_account_logs_in_again_without_duplicate(): void
    {
        $this->fakeGoogleUser('duakali@gmail.com', 'Dua Kali');
        $this->get('/auth/google/callback');
        $this->post('/logout');

        $this->fakeGoogleUser('duakali@gmail.com', 'Dua Kali');
        $countBefore = User::count();
        $response = $this->get('/auth/google/callback');

        $this->assertSame($countBefore, User::count());
        $response->assertRedirect(route('dashboard'));
    }

    public function test_socialite_failure_redirects_to_login_with_error(): void
    {
        Socialite::shouldReceive('driver')->with('google')->andThrow(new \Exception('denied'));

        $countBefore = User::count();
        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Gagal masuk dengan Google. Coba lagi.');
        $this->assertGuest();
        $this->assertSame($countBefore, User::count());
    }
}
