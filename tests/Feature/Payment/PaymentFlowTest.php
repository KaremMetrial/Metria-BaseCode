<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createPaymentUser(): User
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    private function fakeStripe(): void
    {
        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_test_123',
                'client_secret' => 'pi_test_123_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);
    }

    public function test_payment_creation_returns_next_action_from_gateway(): void
    {
        $this->fakeStripe();
        $user = $this->createPaymentUser();

        $response = $this->actingAs($user)->postJson('/api/v1/payments', [
            'amount' => '150.50',
            'currency' => 'EGP',
            'gateway' => 'stripe',
            'description' => 'Trip #42',
        ], ['Idempotency-Key' => (string) Str::uuid()]);

        $response->assertCreated()
            ->assertJsonPath('data.payment.gateway', 'stripe')
            ->assertJsonPath('data.payment.amount.amount', 15050)
            ->assertJsonPath('data.next_action.client_secret', 'pi_test_123_secret');

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'gateway_reference' => 'pi_test_123',
            'amount' => 15050,
        ]);
    }

    public function test_idempotency_key_replays_the_original_response(): void
    {
        $this->fakeStripe();
        $user = $this->createPaymentUser();
        $key = (string) Str::uuid();
        $body = ['amount' => '99.99', 'gateway' => 'stripe'];

        $first = $this->actingAs($user)->postJson('/api/v1/payments', $body, ['Idempotency-Key' => $key]);
        $second = $this->actingAs($user)->postJson('/api/v1/payments', $body, ['Idempotency-Key' => $key]);

        $first->assertCreated();
        $second->assertCreated();
        $second->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(
            $first->json('data.payment.id'),
            $second->json('data.payment.id'),
        );
    }

    public function test_stripe_webhook_with_valid_signature_transitions_the_payment(): void
    {
        $this->fakeStripe();
        config(['payments.gateways.stripe.webhook_secret' => 'whsec_test']);

        $user = $this->createPaymentUser();
        $this->actingAs($user)->postJson('/api/v1/payments', [
            'amount' => '10.00', 'gateway' => 'stripe',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test_123', 'object' => 'payment_intent']],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->call(
            'POST',
            '/api/v1/webhooks/payments/stripe',
            server: [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $payload,
        )->assertOk();

        $this->assertSame(
            PaymentStatus::Succeeded,
            Payment::query()->firstOrFail()->status,
        );
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        config(['payments.gateways.stripe.webhook_secret' => 'whsec_test']);

        $this->postJson('/api/v1/webhooks/payments/stripe', ['type' => 'x'], [
            'Stripe-Signature' => 't=1,v1=deadbeef',
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'invalid_signature');
    }
}
