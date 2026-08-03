<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Domain\Events\UserSessionRevoked;
use Modules\Auth\Domain\Models\User;
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
}
