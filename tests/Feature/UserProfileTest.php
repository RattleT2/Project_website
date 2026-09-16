<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_profile_with_avatar_url(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'avatar' => 'avatars/sample.jpg',
        ]);

        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('name', 'John Doe')
            ->assertJsonPath('avatar', 'avatars/sample.jpg')
            ->assertJsonStructure(['avatar_url']);
    }

    public function test_user_can_update_name_and_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Old Name']);
        $token = auth('api')->login($user);

        $file = UploadedFile::fake()->image('profile.jpg', 200, 200);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json',
        ])->post('/api/auth/me', [
            'name' => 'New Name',
            'avatar' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'New Name');

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->image('old.jpg')->store('avatars', 'public');
        $user = User::factory()->create(['avatar' => $path]);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson('/api/auth/me/avatar');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Foto profil berhasil dihapus.');

        $user->refresh();
        $this->assertNull($user->avatar);
        Storage::disk('public')->assertMissing($path);
    }
}

