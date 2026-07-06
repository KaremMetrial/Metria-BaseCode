<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Events\PaymentSucceeded;
use App\Domain\Payment\Models\Payment;
use App\Domain\Wallet\Events\WalletCredited;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function walletService(): WalletService
    {
        return app(WalletService::class);
    }

    public function test_domain_events_format_realtime_broadcast_payloads_correctly(): void
    {
        $user = User::factory()->create();
        $wallet = $this->walletService()->firstOrCreateFor($user, 'EGP');

        $event = new WalletCredited($wallet, 1500);

        $this->assertSame('wallet.credited', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($wallet->id, $payload['wallet_id']);
        $this->assertSame(1500, $payload['amount']);
        $this->assertArrayHasKey('event_id', $payload);
        $this->assertArrayHasKey('occurred_at', $payload);

        $channels = $event->broadcastOn();
        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-users.'.$user->id, $channels[0]->name);
        $this->assertSame('private-wallets.'.$wallet->id, $channels[1]->name);
    }

    public function test_payment_succeeded_event_broadcasts_on_private_channels(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => 2500,
            'currency' => 'EGP',
            'gateway' => 'stripe',
            'status' => PaymentStatus::Succeeded,
        ]);

        $event = new PaymentSucceeded($payment);

        $this->assertSame('payment.succeeded', $event->broadcastAs());
        $channels = $event->broadcastOn();

        $this->assertSame('private-users.'.$user->id, $channels[0]->name);
        $this->assertSame('private-payments.'.$payment->id, $channels[1]->name);
    }

    public function test_private_channel_authorization_allows_owner_and_rejects_unauthorized_users(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $wallet = $this->walletService()->firstOrCreateFor($owner, 'EGP');

        // Owner can access their user channel
        $response = $this->actingAs($owner)->postJson('/api/v1/broadcasting/auth', [
            'channel_name' => 'private-users.'.$owner->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertSuccessful();

        // Other user cannot access owner's user channel
        $response = $this->actingAs($otherUser)->postJson('/api/v1/broadcasting/auth', [
            'channel_name' => 'private-users.'.$owner->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(403);

        // Owner can access their wallet channel
        $response = $this->actingAs($owner)->postJson('/api/v1/broadcasting/auth', [
            'channel_name' => 'private-wallets.'.$wallet->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertSuccessful();

        // Other user cannot access owner's wallet channel
        $response = $this->actingAs($otherUser)->postJson('/api/v1/broadcasting/auth', [
            'channel_name' => 'private-wallets.'.$wallet->id,
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(403);
    }

    public function test_dual_broadcaster_driver_can_be_resolved_and_dispatches_cleanly(): void
    {
        // Configure dual driver to use log and null for testing without external socket servers
        config(['broadcasting.connections.dual.drivers' => ['log', 'null']]);

        $manager = app(BroadcastManager::class);
        $driver = $manager->connection('dual');

        $this->assertNotNull($driver);

        $driver->broadcast(['test-channel'], 'test-event', ['key' => 'val']);
        $this->assertTrue(true);
    }
}
