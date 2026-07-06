<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Models\User;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_returns_token_and_provisions_defaults(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Kareem',
            'email' => 'kareem@example.com',
            'phone' => '+201000000000',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'locale' => 'ar',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'kareem@example.com')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name'], 'token']]);

        $user = User::query()->where('email', 'kareem@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('customer'));
        $this->assertTrue(Wallet::query()->where('user_id', $user->id)->exists());
    }

    public function test_login_issues_sanctum_token(): void
    {
        $user = User::factory()->create(['password' => 'Secret123!']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_rejects_bad_credentials_with_stable_error_code(): void
    {
        $user = User::factory()->create(['password' => 'Secret123!']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_me_returns_authenticated_profile_with_locale_meta(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('meta.locale', 'ar')
            ->assertJsonPath('meta.direction', 'rtl');
    }
}
