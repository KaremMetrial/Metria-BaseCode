<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use Modules\Auth\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebhookEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function givePermission(User $user, string $permissionName): void
    {
        $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
    }

    public function test_user_with_permission_can_create_and_rotate_webhook_endpoint(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'webhooks.manage');

        Sanctum::actingAs($user);

        // Create endpoint
        $response = $this->postJson('/api/v1/webhook-endpoints', [
            'name' => 'Billing Service',
            'url' => 'https://billing.example.com/webhook',
            'events' => ['payment.succeeded', 'payment.failed'],
            'active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Billing Service')
            ->assertJsonPath('data.url', 'https://billing.example.com/webhook')
            ->assertJsonPath('reveal_secret', true);

        $secret = $response->json('data.secret');
        $this->assertStringStartsWith('whsec_', $secret);
        $endpointId = $response->json('data.id');

        // Rotate secret
        $rotateResponse = $this->postJson("/api/v1/webhook-endpoints/{$endpointId}/rotate-secret");
        $rotateResponse->assertStatus(200)
            ->assertJsonPath('reveal_secret', true);

        $newSecret = $rotateResponse->json('data.secret');
        $this->assertStringStartsWith('whsec_', $newSecret);
        $this->assertNotEquals($secret, $newSecret);
    }

    public function test_user_without_permission_cannot_access_webhook_endpoints(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/webhook-endpoints');
        $response->assertStatus(403);
    }

    /**
     * @dataProvider unsafeUrls
     */
    public function test_endpoint_url_resolving_to_a_private_or_loopback_address_is_rejected(string $url): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'webhooks.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/webhook-endpoints', [
            'name' => 'Attempted SSRF target',
            'url' => $url,
            'events' => ['payment.succeeded'],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('url');
        $this->assertDatabaseMissing('webhook_endpoints', ['url' => $url]);
    }

    public static function unsafeUrls(): array
    {
        return [
            'loopback IP' => ['https://127.0.0.1/webhook'],
            'link-local / cloud metadata' => ['https://169.254.169.254/latest/meta-data/'],
            'localhost hostname' => ['https://localhost/webhook'],
            'private RFC1918 range' => ['https://10.0.0.5/webhook'],
        ];
    }

    public function test_updating_an_endpoint_to_an_unsafe_url_is_also_rejected(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'webhooks.manage');
        Sanctum::actingAs($user);

        $endpointId = $this->postJson('/api/v1/webhook-endpoints', [
            'name' => 'Billing Service',
            'url' => 'https://billing.example.com/webhook',
            'events' => ['payment.succeeded'],
        ])->json('data.id');

        $this->putJson("/api/v1/webhook-endpoints/{$endpointId}", [
            'name' => 'Billing Service',
            'url' => 'https://127.0.0.1/webhook',
            'events' => ['payment.succeeded'],
        ])->assertStatus(422)->assertJsonValidationErrors('url');
    }
}
