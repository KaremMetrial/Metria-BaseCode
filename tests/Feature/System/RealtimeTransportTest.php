<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Domain\Models\User;
use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Infrastructure\Realtime\RealtimeDomainEventListener;
use Modules\Shared\Infrastructure\Realtime\RealtimeEventMapper;
use Modules\Shared\Infrastructure\Realtime\RealtimePublisherContract;
use Modules\Shared\Infrastructure\Realtime\RealtimeRequestSignature;
use Modules\Wallet\Domain\Events\WalletCredited;
use Modules\Wallet\Infrastructure\Services\WalletService;
use Tests\TestCase;

class RealtimeTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_listener_accepts_only_the_serialized_event_argument(): void
    {
        $tenantId = '33333333-3333-4333-8333-333333333333';
        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Transport Tenant', 'slug' => 'transport-tenant', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $wallet = app(WalletService::class)->firstOrCreateFor($user, 'EGP');
        $event = new WalletCredited($wallet, 100);
        $mapper = app(RealtimeEventMapper::class);
        $publisher = \Mockery::mock(RealtimePublisherContract::class);

        $publisher->shouldReceive('publish')->once()->with(\Mockery::on(fn (array $envelope): bool => $envelope['id'] === $event->eventId));

        (new RealtimeDomainEventListener($mapper, $publisher))->handle($event);
    }

    public function test_queued_listener_ignores_unmapped_events(): void
    {
        $event = \Mockery::mock(DomainEvent::class);
        $mapper = app(RealtimeEventMapper::class);
        $publisher = \Mockery::mock(RealtimePublisherContract::class);

        $publisher->shouldNotReceive('publish');

        (new RealtimeDomainEventListener($mapper, $publisher))->handle($event);
    }

    public function test_canonical_signature_binds_method_path_timestamp_nonce_and_body(): void
    {
        $secret = 'realtime-transport-test-secret-that-is-long-enough';
        $timestamp = '1722679200';
        $nonce = '0d62f994-6d25-45ca-b2eb-7081393fce10';
        $body = '{"token":"example"}';
        $signature = RealtimeRequestSignature::sign('POST', '/api/internal/realtime/authenticate', $timestamp, $nonce, $body, $secret);

        $this->assertSame('bde71386cedf8bb56a9251736b2b962f3ea7d17f92104ae884b15032ad79a65c', $signature);
        $this->assertNotSame($signature, RealtimeRequestSignature::sign('GET', '/api/internal/realtime/authenticate', $timestamp, $nonce, $body, $secret));
        $this->assertNotSame($signature, RealtimeRequestSignature::sign('POST', '/api/internal/realtime/authorize-resource', $timestamp, $nonce, $body, $secret));
        $this->assertNotSame($signature, RealtimeRequestSignature::sign('POST', '/api/internal/realtime/authenticate', (string) ((int) $timestamp + 1), $nonce, $body, $secret));
        $this->assertNotSame($signature, RealtimeRequestSignature::sign('POST', '/api/internal/realtime/authenticate', $timestamp, (string) Str::uuid(), $body, $secret));
        $this->assertNotSame($signature, RealtimeRequestSignature::sign('POST', '/api/internal/realtime/authenticate', $timestamp, $nonce, '{"token":"tampered"}', $secret));
    }
}
