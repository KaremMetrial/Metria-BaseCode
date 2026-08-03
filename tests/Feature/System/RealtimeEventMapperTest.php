<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Domain\Events\UserSessionRevoked;
use Modules\Auth\Domain\Models\User;
use Modules\Communication\Domain\Events\CommunicationDomainEvent;
use Modules\Shared\Infrastructure\Realtime\RealtimeEventMapper;
use Modules\Wallet\Domain\Events\WalletCredited;
use Modules\Wallet\Infrastructure\Services\WalletService;
use Tests\TestCase;

class RealtimeEventMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_event_becomes_a_tenant_scoped_public_envelope(): void
    {
        config(['tenancy.enabled' => true]);
        $tenantId = '11111111-1111-4111-8111-111111111111';
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Realtime Test Tenant',
            'slug' => 'realtime-test-tenant',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $wallet = app(WalletService::class)->firstOrCreateFor($user, 'EGP');

        $envelope = app(RealtimeEventMapper::class)->map(new WalletCredited($wallet, 1500));

        $this->assertNotNull($envelope);
        $this->assertSame('wallet.credited', $envelope['name']);
        $this->assertSame($user->tenant_id, $envelope['tenant_id']);
        $this->assertSame([$user->id], $envelope['audience']['user_ids']);
        $this->assertArrayNotHasKey('password', $envelope['payload']);
    }

    public function test_unscoped_event_is_never_made_global(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);
        $wallet = app(WalletService::class)->firstOrCreateFor($user, 'EGP');

        $this->assertNull(app(RealtimeEventMapper::class)->map(new WalletCredited($wallet, 1500)));
    }

    public function test_session_revocation_targets_only_the_revoked_token_owner(): void
    {
        $tenantId = '22222222-2222-4222-8222-222222222222';
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Realtime Security Tenant',
            'slug' => 'realtime-security-tenant',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create(['tenant_id' => $tenantId]);

        $envelope = app(RealtimeEventMapper::class)->map(new UserSessionRevoked($user, 'session-1', '42'));

        $this->assertNotNull($envelope);
        $this->assertSame('security.session_revoked', $envelope['name']);
        $this->assertSame([$user->id], $envelope['audience']['user_ids']);
        $this->assertSame('42', $envelope['payload']['token_id']);
    }

    public function test_communication_conversation_created_is_a_minimum_safe_user_hint(): void
    {
        $tenantId = '33333333-3333-4333-8333-333333333333';
        $conversationId = '44444444-4444-4444-8444-444444444444';
        $actorA = '55555555-5555-4555-8555-555555555555';
        $actorB = '66666666-6666-4666-8666-666666666666';

        $envelope = app(RealtimeEventMapper::class)->map(new CommunicationDomainEvent('communication.conversation.created', [
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'conversation_version' => 1,
            'type' => 'direct',
            'state' => 'active',
            'participant_ids' => [$actorA, $actorB],
            'title' => 'Must never reach Socket.IO',
        ]));

        $this->assertNotNull($envelope);
        $this->assertSame('communication.conversation.created', $envelope['name']);
        $this->assertSame(['type' => 'users', 'user_ids' => [$actorA, $actorB]], $envelope['audience']);
        $this->assertSame(['type' => 'conversation', 'id' => $conversationId], $envelope['subject']);
        $this->assertSame([
            'conversation_id' => $conversationId,
            'conversation_version' => 1,
            'type' => 'direct',
            'state' => 'active',
        ], $envelope['payload']);
    }

    public function test_communication_message_created_targets_only_its_conversation_room(): void
    {
        $tenantId = '77777777-7777-4777-8777-777777777777';
        $conversationId = '88888888-8888-4888-8888-888888888888';
        $messageId = '99999999-9999-4999-8999-999999999999';
        $authorId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

        $envelope = app(RealtimeEventMapper::class)->map(new CommunicationDomainEvent('communication.message.created', [
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'conversation_version' => 2,
            'message_id' => $messageId,
            'sequence' => 1,
            'kind' => 'text',
            'revision' => 1,
            'author_actor_id' => $authorId,
            'content' => ['text' => 'Must never reach Socket.IO by default'],
        ]));

        $this->assertNotNull($envelope);
        $this->assertSame([
            'type' => 'resource',
            'resource_type' => 'conversation',
            'resource_id' => $conversationId,
        ], $envelope['audience']);
        $this->assertSame(['type' => 'message', 'id' => $messageId], $envelope['subject']);
        $this->assertArrayNotHasKey('content', $envelope['payload']);
        $this->assertSame(1, $envelope['payload']['sequence']);
    }
}
