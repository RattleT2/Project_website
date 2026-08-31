<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_google_url(): void
    {
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless->redirect->getTargetUrl')
            ->andReturn('https://accounts.google.com/o/oauth2/v2/auth?test=1');

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google');

        $response->assertStatus(200);
        $response->assertJsonStructure(['url']);
    }

    public function test_handle_google_callback_creates_and_logins_user(): void
    {
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('usergoogle@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('Google User Test');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless->user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'user',
            'access_token',
            'token_type',
            'expires_in',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'usergoogle@example.com',
            'role' => 'pelapor',
        ]);
    }

    public function test_handle_google_callback_preserves_existing_admin_role(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin_google@kominfo.go.id',
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('admin_google@kominfo.go.id');
        $abstractUser->shouldReceive('getName')->andReturn('Admin Google User');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless->user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertStatus(200);

        // Verify that existing admin role was NOT overwritten to pelapor
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'admin_google@kominfo.go.id',
            'role' => 'admin',
        ]);
    }
}
