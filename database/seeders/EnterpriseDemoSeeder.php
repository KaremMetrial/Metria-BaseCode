<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Domain\Models\User;

/**
 * A deterministic, idempotent dataset for local demos, API exploration and QA.
 *
 * All credentials are deliberately development-only. The seeder uses stable
 * IDs and natural keys so it can be safely re-run with `php artisan db:seed`.
 */
final class EnterpriseDemoSeeder extends Seeder
{
    private const ACME_TENANT = '11111111-1111-4111-8111-111111111111';
    private const NORTHSTAR_TENANT = '22222222-2222-4222-8222-222222222222';
    private const ADMIN = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
    private const FINANCE = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2';
    private const SUPPORT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3';
    private const CUSTOMER = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4';

    public function run(): void
    {
        $now = now();
        $this->seedTenants($now);
        $this->seedCurrencies($now);
        $this->seedUsersAndRoles($now);
        $this->seedFinance($now);
        $this->seedGovernanceAndIntegrations($now);
        $this->seedMediaAndWebhooks($now);
        $this->seedCommunication($now);
    }

    private function seedTenants($now): void
    {
        $this->upsert('tenants', ['id' => self::ACME_TENANT], [
            'name' => 'Acme Egypt', 'slug' => 'acme-eg', 'active' => true,
            'metadata' => json_encode(['plan' => 'enterprise', 'region' => 'EG', 'timezone' => 'Africa/Cairo']),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->upsert('tenants', ['id' => self::NORTHSTAR_TENANT], [
            'name' => 'Northstar UAE', 'slug' => 'northstar-uae', 'active' => true,
            'metadata' => json_encode(['plan' => 'growth', 'region' => 'AE', 'timezone' => 'Asia/Dubai']),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function seedCurrencies($now): void
    {
        foreach ([
            ['EGP', ['en' => 'Egyptian Pound', 'ar' => 'جنيه مصري'], ['en' => 'EGP', 'ar' => 'ج.م'], 2, true],
            ['AED', ['en' => 'UAE Dirham', 'ar' => 'درهم إماراتي'], ['en' => 'AED', 'ar' => 'د.إ'], 2, false],
            ['USD', ['en' => 'US Dollar', 'ar' => 'دولار أمريكي'], ['en' => '$', 'ar' => '$'], 2, false],
            ['SAR', ['en' => 'Saudi Riyal', 'ar' => 'ريال سعودي'], ['en' => 'SAR', 'ar' => 'ر.س'], 2, false],
        ] as [$code, $name, $symbol, $minorUnits, $default]) {
            $this->upsert('currencies', ['code' => $code], [
                'name' => json_encode($name), 'symbol' => json_encode($symbol), 'minor_units' => $minorUnits,
                'is_active' => true, 'is_default' => $default, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([['AED', '13.61000000000000'], ['USD', '50.00000000000000'], ['SAR', '13.33000000000000']] as $index => [$currency, $rate]) {
            $id = sprintf('30000000-0000-4000-8000-%012d', $index + 1);
            $this->upsert('currency_exchange_rates', ['id' => $id], [
                'currency_code' => $currency, 'rate_to_base' => $rate, 'provider_name' => 'demo-reference-rate',
                'provider_version' => '2026-08', 'is_manual' => true, 'is_locked' => false,
                'effective_at' => $now->copy()->subDay(), 'expires_at' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function seedUsersAndRoles($now): void
    {
        $password = Hash::make('ChangeMe!2026');
        $users = [
            [self::ADMIN, self::ACME_TENANT, 'Mariam Hassan', 'admin@acme-eg.test', '+201001234567', 'en', 'super-admin'],
            [self::FINANCE, self::ACME_TENANT, 'Omar Khalil', 'finance@acme-eg.test', '+201101234567', 'en', 'finance'],
            [self::SUPPORT, self::ACME_TENANT, 'Salma Adel', 'support@acme-eg.test', '+201201234567', 'ar', 'support'],
            [self::CUSTOMER, self::ACME_TENANT, 'Youssef Nabil', 'customer@acme-eg.test', '+201301234567', 'ar', 'customer'],
        ];

        foreach ($users as [$id, $tenantId, $name, $email, $phone, $locale, $role]) {
            $this->upsert('users', ['id' => $id], [
                'tenant_id' => $tenantId, 'name' => $name, 'email' => $email, 'phone' => $phone,
                'password' => $password, 'locale' => $locale, 'email_verified_at' => $now, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->whereNull('tenant_id')->value('id');
            if ($roleId !== null) {
                $this->upsert('model_has_roles', [
                    'tenant_id' => $tenantId, 'role_id' => $roleId, 'model_type' => User::class, 'model_id' => $id,
                ], []);
            }
        }

        $this->upsert('fcm_device_tokens', ['device_token' => 'demo-fcm-token-customer-001'], [
            'id' => '40000000-0000-4000-8000-000000000001', 'user_id' => self::CUSTOMER,
            'device_id' => 'iphone-15-demo', 'device_name' => 'iPhone 15', 'platform' => 'ios', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function seedFinance($now): void
    {
        $walletId = '50000000-0000-4000-8000-000000000001';
        $this->upsert('wallets', ['user_id' => self::CUSTOMER], [
            'id' => $walletId, 'tenant_id' => self::ACME_TENANT, 'balance' => 125000, 'held' => 15000,
            'currency' => 'EGP', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->upsert('wallet_transactions', ['id' => '51000000-0000-4000-8000-000000000001'], [
            'wallet_id' => $walletId, 'type' => 'credit', 'amount' => 140000, 'balance_after' => 140000,
            'held_after' => 0, 'reference_type' => 'payment', 'reference_id' => '60000000-0000-4000-8000-000000000001',
            'description' => 'Wallet top-up via card', 'metadata' => json_encode(['source' => 'stripe']), 'created_at' => $now->copy()->subDays(2),
        ]);
        $this->upsert('wallet_transactions', ['id' => '51000000-0000-4000-8000-000000000002'], [
            'wallet_id' => $walletId, 'type' => 'hold', 'amount' => 15000, 'balance_after' => 140000,
            'held_after' => 15000, 'reference_type' => 'order', 'reference_id' => 'ORD-1042',
            'description' => 'Delivery escrow hold', 'metadata' => json_encode(['order_number' => 'ORD-1042']), 'created_at' => $now->copy()->subDay(),
        ]);
        $this->upsert('payments', ['id' => '60000000-0000-4000-8000-000000000001'], [
            'tenant_id' => self::ACME_TENANT, 'user_id' => self::CUSTOMER, 'gateway' => 'stripe', 'gateway_reference' => 'pi_demo_topup_001',
            'amount' => 140000, 'refunded_amount' => 0, 'currency' => 'EGP', 'source_currency' => 'EGP', 'target_currency' => 'EGP',
            'converted_amount' => 140000, 'converted_amount_decimal' => '1400.0000', 'exchange_rate' => '1.00000000000000',
            'rate_provider' => 'demo-reference-rate', 'conversion_direction' => 'multiply', 'rounding_mode_used' => 'half_up',
            'conversion_algorithm_version' => 'v1', 'rate_captured_at' => $now->copy()->subDays(2), 'status' => 'succeeded',
            'description' => 'Wallet top-up', 'metadata' => json_encode(['reference_code' => 'TOPUP-1001']), 'paid_at' => $now->copy()->subDays(2), 'created_at' => $now->copy()->subDays(2), 'updated_at' => $now,
        ]);
    }

    private function seedGovernanceAndIntegrations($now): void
    {
        foreach ([
            ['company.profile', ['legal_name' => 'Acme Egypt LLC', 'support_email' => 'support@acme-eg.test'], 'Tenant business profile'],
            ['billing.invoice_prefix', ['value' => 'ACME-EG'], 'Prefix used for customer invoices'],
        ] as [$key, $value, $description]) {
            $this->upsert('settings', ['tenant_id' => self::ACME_TENANT, 'key' => $key], [
                'value' => json_encode($value), 'description' => $description, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->upsert('feature_flags', ['name' => 'new_checkout'], [
            'enabled' => true, 'percentage' => 25, 'allowed_user_ids' => json_encode([self::ADMIN, self::CUSTOMER]),
            'description' => 'Gradual rollout of the new checkout experience', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->upsert('approval_requests', ['id' => '70000000-0000-4000-8000-000000000001'], [
            'tenant_id' => self::ACME_TENANT, 'action' => 'payments.refund', 'payload' => json_encode(['payment_id' => '60000000-0000-4000-8000-000000000001', 'amount' => 2500]),
            'status' => 'pending', 'reason' => 'Customer goodwill refund', 'requested_by' => self::SUPPORT, 'decided_by' => null,
            'decided_at' => null, 'created_at' => $now->copy()->subHours(3), 'updated_at' => $now->copy()->subHours(3),
        ]);
        $this->upsert('audit_logs', ['id' => '71000000-0000-4000-8000-000000000001'], [
            'tenant_id' => self::ACME_TENANT, 'user_id' => self::ADMIN, 'action' => 'governance.flag.updated',
            'auditable_type' => 'Modules\\Governance\\Domain\\Models\\FeatureFlag', 'auditable_id' => '1',
            'old_values' => json_encode(['enabled' => false]), 'new_values' => json_encode(['enabled' => true]),
            'context' => json_encode(['source' => 'enterprise-demo-seeder']), 'ip_address' => '127.0.0.1', 'user_agent' => 'Seeder', 'created_at' => $now,
        ]);
        $this->upsert('oauth_providers', ['tenant_id' => self::ACME_TENANT, 'provider' => 'google'], [
            'id' => '72000000-0000-4000-8000-000000000001', 'client_id' => 'demo-google-client-id', 'client_secret' => 'demo-only-not-a-real-secret',
            'redirect_url' => 'http://localhost:8000/api/v1/auth/social/google/callback', 'scopes' => json_encode(['openid', 'email', 'profile']),
            'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function seedMediaAndWebhooks($now): void
    {
        $blobId = '80000000-0000-4000-8000-000000000001';
        $sha = hash('sha256', 'enterprise-demo-avatar');
        $this->upsert('media_blobs', ['tenant_id' => self::ACME_TENANT, 'sha256' => $sha], [
            'id' => $blobId, 'disk' => 'local', 'path' => 'demo/acme/avatar.png', 'filename' => 'avatar.png', 'original_filename' => 'mariam-avatar.png',
            'mime_type' => 'image/png', 'size' => 48231, 'virus_status' => 'clean', 'virus_scan_details' => json_encode(['engine' => 'demo', 'result' => 'clean']),
            'storage_provider' => 'local', 'uploaded_at' => $now, 'verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->upsert('media', ['id' => '81000000-0000-4000-8000-000000000001'], [
            'tenant_id' => self::ACME_TENANT, 'media_blob_id' => $blobId, 'mediable_type' => User::class, 'mediable_id' => self::ADMIN,
            'media_type' => 'image', 'purpose' => 'avatar', 'is_public' => false, 'status' => 'active', 'checksum' => $sha,
            'custom_properties' => json_encode(['filename' => 'mariam-avatar.png']), 'moderation_status' => 'approved',
            'activated_at' => $now, 'created_by' => self::ADMIN, 'updated_by' => self::ADMIN, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->upsert('webhook_endpoints', ['tenant_id' => self::ACME_TENANT, 'name' => 'Operations event bus'], [
            'id' => '82000000-0000-4000-8000-000000000001', 'url' => 'https://webhook.example.test/acme/events',
            'secret' => 'whsec_demo_only', 'events' => json_encode(['payment.succeeded', 'wallet.credited']), 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->upsert('webhook_deliveries', ['id' => '83000000-0000-4000-8000-000000000001'], [
            'endpoint_id' => '82000000-0000-4000-8000-000000000001', 'event' => 'payment.succeeded',
            'payload' => json_encode(['payment_id' => '60000000-0000-4000-8000-000000000001']), 'status' => 'success', 'attempts' => 1,
            'response_status' => 202, 'response_body' => '{"accepted":true}', 'delivered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function seedCommunication($now): void
    {
        $conversationId = '90000000-0000-4000-8000-000000000001';
        $this->upsert('communication_conversations', ['id' => $conversationId], [
            'tenant_id' => self::ACME_TENANT, 'type' => 'private_group', 'state' => 'active', 'title' => 'Customer support — ORD-1042',
            'created_by' => self::SUPPORT, 'direct_key' => null, 'next_sequence' => 1, 'version' => 1,
            'settings' => json_encode(['retention_days' => 365]), 'created_at' => $now->copy()->subHour(), 'updated_at' => $now,
        ]);
        foreach ([[self::SUPPORT, 'moderator'], [self::CUSTOMER, 'member']] as $index => [$actorId, $role]) {
            $this->upsert('communication_memberships', ['conversation_id' => $conversationId, 'actor_id' => $actorId], [
                'id' => sprintf('91000000-0000-4000-8000-%012d', $index + 1), 'tenant_id' => self::ACME_TENANT,
                'role' => $role, 'state' => 'active', 'last_read_sequence' => 1, 'last_delivered_sequence' => 1,
                'version' => 1, 'created_at' => $now->copy()->subHour(), 'updated_at' => $now,
            ]);
        }
        $messageId = '92000000-0000-4000-8000-000000000001';
        $this->upsert('communication_messages', ['id' => $messageId], [
            'tenant_id' => self::ACME_TENANT, 'conversation_id' => $conversationId, 'author_actor_id' => self::SUPPORT,
            'sequence' => 1, 'kind' => 'text', 'content' => json_encode(['text' => 'Your delivery is scheduled for today between 2:00 PM and 4:00 PM.']),
            'state' => 'active', 'revision' => 1, 'client_message_id' => '93000000-0000-4000-8000-000000000001',
            'created_at' => $now->copy()->subMinutes(45), 'updated_at' => $now->copy()->subMinutes(45),
        ]);
        $this->upsert('communication_sync_changes', ['conversation_id' => $conversationId, 'change_version' => 1], [
            'id' => '94000000-0000-4000-8000-000000000001', 'tenant_id' => self::ACME_TENANT,
            'change_type' => 'message.created', 'message_id' => $messageId, 'payload' => json_encode(['sequence' => 1]),
            'created_at' => $now->copy()->subMinutes(45), 'updated_at' => $now->copy()->subMinutes(45),
        ]);
    }

    /** @param array<string, mixed> $keys @param array<string, mixed> $values */
    private function upsert(string $table, array $keys, array $values): void
    {
        DB::table($table)->updateOrInsert($keys, $values);
    }
}
