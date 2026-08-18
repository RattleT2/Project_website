<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
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
        // Disable captcha validation for this test
        config(['captcha.disable' => true]);

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            // Add dummy captcha fields to pass request validation
            'captcha' => 'dummy-captcha',
            'captcha_key' => 'dummy-key',
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
        // Disable captcha validation for this test
        config(['captcha.disable' => true]);

        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'status' => 'aktif',
        ]);

        $loginData = [
            'email' => $user->email, 
            'password' => 'password',
            // Add dummy captcha fields
            'captcha' => 'dummy-captcha',
            'captcha_key' => 'dummy-key',
        ];

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
        // Disable captcha validation for this test
        config(['captcha.disable' => true]);

        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'status' => 'non-aktif',
        ]);

        $loginData = [
            'email' => $user->email, 
            'password' => 'password',
            // Add dummy captcha fields
            'captcha' => 'dummy-captcha',
            'captcha_key' => 'dummy-key',
        ];

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
            ->assertJsonStructure(['captcha_img', 'captcha_key']);
    }

    /**
     * Test password reset link can be requested.
     *
     * @return void
     */
    public function test_user_can_request_password_reset_link()
    {
        // Disable captcha validation for this test
        config(['captcha.disable' => true]);
        
        Notification::fake();

        $user = User::factory()->create(['status' => 'aktif']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
            // Add dummy captcha fields to pass request validation
            'captcha' => 'dummy-captcha',
            'captcha_key' => 'dummy-key',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Jika email terdaftar, tautan reset password telah dikirim.']);

        Notification::assertSentTo(
            $user,
            ResetPassword::class
        );
    }
}
