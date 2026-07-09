<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Auth\Models\User;
use App\Domain\Currency\Models\Currency;
use App\Domain\Governance\Models\ApprovalRequest;
use App\Domain\Governance\Models\AuditLog;
use App\Domain\Governance\Models\FeatureFlag;
use App\Domain\Governance\Models\Setting;
use App\Domain\Integration\Models\OAuthProvider;
use App\Domain\Territory\Models\Country;
use App\Domain\Territory\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnterpriseRbacPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure basic permissions exist for testing
        Permission::firstOrCreate(['name' => 'admin.super', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'integrations.oauth.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'integrations.oauth.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'governance.settings.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'governance.settings.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'governance.flags.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'territories.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'territories.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'currencies.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'currencies.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'media.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'media.upload', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'media.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'payments.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'payments.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'payments.refund', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wallets.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wallets.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'webhooks.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'webhooks.manage', 'guard_name' => 'web']);
    }

    public function test_super_admin_bypasses_all_policy_checks_via_before_hook(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo('admin.super');

        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', OAuthProvider::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('create', OAuthProvider::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', Setting::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', Setting::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', FeatureFlag::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', Country::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('create', Country::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', Currency::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('delete', Currency::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('create', \App\Domain\Media\Models\Media::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('refund', \App\Domain\Payment\Models\Payment::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage', \App\Domain\Wallet\Models\Wallet::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('create', \App\Domain\Webhook\Models\WebhookEndpoint::class));
    }

    public function test_unprivileged_user_is_denied_management_abilities(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', OAuthProvider::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', OAuthProvider::class));
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', Setting::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', Setting::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', Country::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', Currency::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', \App\Domain\Media\Models\Media::class));
        $this->assertFalse(Gate::forUser($user)->allows('refund', \App\Domain\Payment\Models\Payment::class));
        $this->assertFalse(Gate::forUser($user)->allows('manage', \App\Domain\Wallet\Models\Wallet::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', \App\Domain\Webhook\Models\WebhookEndpoint::class));
    }

    public function test_modular_permission_grants_specific_abilities_without_super_admin(): void
    {
        $oauthViewer = User::factory()->create();
        $oauthViewer->givePermissionTo('integrations.oauth.view');

        $this->assertTrue(Gate::forUser($oauthViewer)->allows('viewAny', OAuthProvider::class));
        $this->assertFalse(Gate::forUser($oauthViewer)->allows('create', OAuthProvider::class));

        $countryManager = User::factory()->create();
        $countryManager->givePermissionTo(['territories.view', 'territories.manage']);

        $this->assertTrue(Gate::forUser($countryManager)->allows('viewAny', Country::class));
        $this->assertTrue(Gate::forUser($countryManager)->allows('create', Country::class));
        $this->assertFalse(Gate::forUser($countryManager)->allows('create', Currency::class));
    }

    public function test_feature_flag_evaluation_view_is_allowed_for_all_users(): void
    {
        $user = User::factory()->create();

        // Any authenticated user can evaluate a flag for their own context
        $this->assertTrue(Gate::forUser($user)->allows('view', FeatureFlag::class));
        // But only flag managers can list all or toggle
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', FeatureFlag::class));
        $this->assertFalse(Gate::forUser($user)->allows('toggle', FeatureFlag::class));
    }

    public function test_media_payment_wallet_and_webhook_modular_permissions(): void
    {
        $mediaUploader = User::factory()->create();
        $mediaUploader->givePermissionTo('media.upload');
        $this->assertTrue(Gate::forUser($mediaUploader)->allows('create', \App\Domain\Media\Models\Media::class));
        $this->assertFalse(Gate::forUser($mediaUploader)->allows('refund', \App\Domain\Payment\Models\Payment::class));

        $financeUser = User::factory()->create();
        $financeUser->givePermissionTo(['payments.refund', 'wallets.manage']);
        $this->assertTrue(Gate::forUser($financeUser)->allows('refund', \App\Domain\Payment\Models\Payment::class));
        $this->assertTrue(Gate::forUser($financeUser)->allows('manage', \App\Domain\Wallet\Models\Wallet::class));
        $this->assertFalse(Gate::forUser($financeUser)->allows('create', \App\Domain\Webhook\Models\WebhookEndpoint::class));

        $webhookManager = User::factory()->create();
        $webhookManager->givePermissionTo('webhooks.manage');
        $this->assertTrue(Gate::forUser($webhookManager)->allows('create', \App\Domain\Webhook\Models\WebhookEndpoint::class));
        $this->assertTrue(Gate::forUser($webhookManager)->allows('update', \App\Domain\Webhook\Models\WebhookEndpoint::class));
        $this->assertFalse(Gate::forUser($webhookManager)->allows('wallets.manage', \App\Domain\Wallet\Models\Wallet::class));
    }

    public function test_form_request_authorization_boundaries(): void
    {
        $confirmMfaRequest = new \App\Domain\Auth\Http\Requests\ConfirmMfaRequest();
        $this->assertFalse($confirmMfaRequest->authorize());

        $disableMfaRequest = new \App\Domain\Auth\Http\Requests\DisableMfaRequest();
        $this->assertFalse($disableMfaRequest->authorize());

        $updateFcmRequest = new \App\Domain\Auth\Http\Requests\UpdateFcmTokenRequest();
        $this->assertFalse($updateFcmRequest->authorize());

        $user = User::factory()->create();
        $updateSettingRequest = new \App\Domain\Governance\Http\Requests\UpdateSettingRequest();
        $updateSettingRequest->setUserResolver(fn () => $user);
        $this->assertFalse($updateSettingRequest->authorize());

        $settingManager = User::factory()->create();
        $settingManager->givePermissionTo('governance.settings.manage');
        $updateSettingRequest->setUserResolver(fn () => $settingManager);
        $this->assertTrue($updateSettingRequest->authorize());
    }

    public function test_payment_policy_view_any_allows_payment_creators(): void
    {
        $customer = User::factory()->create();
        $customer->givePermissionTo('payments.create');

        $this->assertTrue(Gate::forUser($customer)->allows('viewAny', \App\Domain\Payment\Models\Payment::class));
    }

    public function test_cross_tenant_wallet_approval_and_payment_operations(): void
    {
        // 1. Enable Tenancy
        config(['tenancy.enabled' => true]);

        // Insert Tenant records to satisfy foreign key constraints
        \Illuminate\Support\Facades\DB::table('tenants')->insert([
            ['id' => 'tenant-1', 'name' => 'Tenant 1', 'slug' => 'tenant-1', 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'tenant-2', 'name' => 'Tenant 2', 'slug' => 'tenant-2', 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create users on different tenants
        $tenant1User = User::factory()->create(['tenant_id' => 'tenant-1']);
        $tenant2User = User::factory()->create(['tenant_id' => 'tenant-2']);

        // Create wallets
        $walletService = app(\App\Domain\Wallet\Services\WalletService::class);
        $w1 = $walletService->firstOrCreateFor($tenant1User, 'USD');
        $w2 = $walletService->firstOrCreateFor($tenant2User, 'USD');

        $this->assertEquals('tenant-1', $w1->tenant_id);
        $this->assertEquals('tenant-2', $w2->tenant_id);

        // Credit w1
        $walletService->credit($w1, \App\Core\Support\Money::of(1000, 'USD'));

        // Settle from tenant-1 to tenant-2 wallet under tenant-1 context
        app(\App\Core\Tenancy\TenantManager::class)->set('tenant-1');
        
        $walletService->hold($w1, \App\Core\Support\Money::of(500, 'USD'));

        // Settle hold from w1 to w2 should not fail due to tenant scopes
        $walletService->settleHold($w1, $w2, \App\Core\Support\Money::of(500, 'USD'), 'Cross-tenant settlement');

        $this->assertEquals(500, $w1->fresh()->balance);
        $this->assertEquals(500, $w2->fresh()->balance);

        // 2. Cross-tenant approvals
        $admin = User::factory()->create(['tenant_id' => 'tenant-2']);
        $admin->givePermissionTo('admin.super');

        $approvalService = app(\App\Domain\Governance\Services\ApprovalService::class);
        $approvalRequest = $approvalService->request('payments.refund', [
            'payment_id' => (string) \Illuminate\Support\Str::uuid(),
            'amount' => 100,
        ], $tenant1User);

        // Admin (in tenant-2 context) approves request (in tenant-1)
        app(\App\Core\Tenancy\TenantManager::class)->set('tenant-2');
        $updatedRequest = $approvalService->approve($approvalRequest, $admin);

        $this->assertEquals(\App\Domain\Governance\Enums\ApprovalStatus::Failed, $updatedRequest->status);
    }
}
