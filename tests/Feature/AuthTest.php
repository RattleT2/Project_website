<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\CaptchaRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user registration.
     *
     * @return void
     */
    public function test_user_can_register()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'access_token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    /**
     * Test user login.
     *
     * @return void
     */
    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'status' => 'aktif',
        ]);

        $loginData = ['email' => $user->email, 'password' => 'password'];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'access_token',
            ]);
    }
    
    /**
     * Test inactive user cannot login.
     *
     * @return void
     */
    public function test_user_cannot_login_with_inactive_status()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'status' => 'non-aktif',
        ]);

        $loginData = ['email' => $user->email, 'password' => 'password'];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Akun non-aktif. Hubungi admin.']);
    }

    /**
     * Test captcha generation.
     *
     * @return void
     */
    public function test_captcha_can_be_generated()
    {
        $response = $this->getJson('/api/captcha');

        $response->assertStatus(200)
            ->assertJsonStructure(['captcha', 'key']);
    }

    /**
     * Test password reset link can be requested.
     *
     * @return void
     */
    public function test_user_can_request_password_reset_link_with_valid_captcha()
    {
        // Fake the notification system
        Notification::fake();

        // Create a user
        $user = User::factory()->create(['status' => 'aktif']);

        // Mock the CaptchaRule to always pass
        $this->mock(CaptchaRule::class, function ($mock) {
            $mock->shouldReceive('passes')->andReturn(true);
        });

        // Send password reset request
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
            'captcha' => 'dummy-captcha-value', // Value doesn't matter due to mocking
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Jika email terdaftar, tautan reset password telah dikirim.']);

        // Assert that a ResetPassword notification was sent to the user
        Notification::assertSentTo(
            $user,
            ResetPassword::class
        );
    }
}
