<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserSocialIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EnterpriseSocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => 'mock-google-id',
            'services.google.redirect' => 'https://example.com/google/callback',
            'services.apple.client_id' => 'mock-apple-id',
            'services.apple.redirect' => 'https://example.com/apple/callback',
            'services.github.client_id' => 'mock-github-id',
            'services.github.redirect' => 'https://example.com/github/callback',
        ]);
    }

    public function test_social_callback_creates_new_user_and_links_identity(): void
    {
        $response = $this->postJson('/api/v1/auth/social/google/callback', [
            'id' => 'google_12345',
            'email' => 'newuser@example.com',
            'name' => 'Google User',
            'token' => 'mock_token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_new', true)
            ->assertJsonPath('data.user.email', 'newuser@example.com')
            ->assertJsonStructure(['data' => ['user', 'token', 'is_new']]);

        $this->assertDatabaseHas('user_social_identities', [
            'provider' => 'google',
            'provider_user_id' => 'google_12345',
            'provider_email' => 'newuser@example.com',
        ]);
    }

    public function test_social_callback_links_to_existing_user_by_email(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => 'Secret123!',
        ]);

        $response = $this->postJson('/api/v1/auth/social/apple/callback', [
            'id' => 'apple_99999',
            'email' => 'existing@example.com',
            'name' => 'Apple User',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_new', false)
            ->assertJsonPath('data.user.id', $existingUser->id);

        $this->assertDatabaseHas('user_social_identities', [
            'user_id' => $existingUser->id,
            'provider' => 'apple',
            'provider_user_id' => 'apple_99999',
        ]);
    }

    public function test_user_can_link_additional_social_provider(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/social/github/link', [
            'id' => 'github_555',
            'email' => $user->email,
            'name' => 'GitHub Dev',
        ])->assertOk();

        $this->assertDatabaseHas('user_social_identities', [
            'user_id' => $user->id,
            'provider' => 'github',
        ]);
    }

    public function test_user_cannot_unlink_only_identity_without_password(): void
    {
        $user = User::factory()->create(['password' => null]);
        UserSocialIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g_only_id',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/social/google/unlink')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cannot_unlink_only_identity');
    }

    public function test_user_can_unlink_social_identity_when_password_exists(): void
    {
        $user = User::factory()->create(['password' => 'Secret123!']);
        UserSocialIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g_to_unlink',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/social/google/unlink')
            ->assertOk();

        $this->assertDatabaseMissing('user_social_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    public function test_admin_can_manage_oauth_providers(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'admin.super', 'guard_name' => 'web']);
        $admin->givePermissionTo($permission);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/auth/oauth-providers', [
            'provider' => 'custom_idp',
            'client_id' => 'client_abc',
            'client_secret' => 'super_secret_key',
            'redirect_url' => 'https://example.com/callback',
            'is_enabled' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.provider.provider', 'custom_idp');

        $this->assertDatabaseHas('oauth_providers', [
            'provider' => 'custom_idp',
            'client_id' => 'client_abc',
        ]);
    }
}
