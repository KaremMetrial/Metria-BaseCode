<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RBAC baseline. Permissions follow `resource.action` naming so middleware
 * reads naturally:  ->middleware('permission:payments.refund')
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Super Admin override
            'admin.super',
            // Users & Sessions
            'users.view', 'users.create', 'users.update', 'users.delete',
            'sessions.view', 'sessions.manage',
            // Roles
            'roles.view', 'roles.manage',
            // Integrations
            'integrations.oauth.view', 'integrations.oauth.manage',
            // Payments
            'payments.view', 'payments.create', 'payments.refund', 'payments.manage',
            // Wallets
            'wallets.view', 'wallets.adjust', 'wallets.manage',
            // Currencies
            'currencies.view', 'currencies.manage',
            // Territories & Logistics
            'territories.view', 'territories.manage',
            'zones.view', 'zones.manage',
            'couriers.track',
            // Governance
            'governance.settings.view', 'governance.settings.manage',
            'governance.flags.manage',
            'governance.audit.view',
            'governance.approvals.view', 'governance.approvals.decide',
            // Media
            'media.view', 'media.upload', 'media.delete', 'media.manage',
            // Webhooks
            'webhooks.view', 'webhooks.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'super-admin' => $permissions,
            'admin' => array_diff($permissions, ['roles.manage', 'admin.super']),
            'finance' => [
                'payments.view', 'payments.refund', 'payments.manage',
                'wallets.view', 'wallets.adjust', 'wallets.manage',
                'currencies.view', 'currencies.manage',
                'governance.approvals.view', 'governance.approvals.decide',
            ],
            'logistics-dispatcher' => [
                'territories.view', 'territories.manage',
                'zones.view', 'zones.manage',
                'couriers.track',
            ],
            'courier' => [
                'territories.view', 'zones.view', 'couriers.track',
            ],
            'support' => [
                'users.view', 'sessions.view', 'payments.view', 'wallets.view',
                'territories.view', 'zones.view', 'currencies.view', 'integrations.oauth.view',
                'media.view', 'webhooks.view',
            ],
            'user' => [
                'currencies.view', 'territories.view', 'zones.view', 'media.view', 'media.upload', 'payments.create',
            ],
            'customer' => [
                'currencies.view', 'territories.view', 'zones.view', 'media.view', 'media.upload', 'payments.create',
            ],
        ];

        foreach ($roles as $role => $rolePermissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($rolePermissions);
        }
    }
}
