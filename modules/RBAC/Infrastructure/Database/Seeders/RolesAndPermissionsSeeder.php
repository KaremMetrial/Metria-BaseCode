<?php

declare(strict_types=1);

namespace Modules\RBAC\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\RBAC\Domain\Models\Role;
use Spatie\Permission\Models\Permission;
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
            // Legacy Roles (kept for backward compat)
            'roles.view', 'roles.manage',
            // RBAC Engine
            'rbac.roles.view', 'rbac.roles.manage',
            'rbac.permissions.view', 'rbac.permissions.manage',
            'rbac.users.view', 'rbac.users.manage',
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
            // Communication durable core
            'communication.conversations.view', 'communication.conversations.create', 'communication.conversations.manage',
            'communication.messages.create',
            // Webhooks
            'webhooks.view', 'webhooks.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'super-admin' => $permissions,
            'admin' => array_diff($permissions, ['roles.manage', 'rbac.roles.manage', 'rbac.users.manage', 'admin.super']),
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
                'communication.conversations.view', 'communication.conversations.create', 'communication.messages.create',
            ],
            'customer' => [
                'currencies.view', 'territories.view', 'zones.view', 'media.view', 'media.upload', 'payments.create',
                'communication.conversations.view', 'communication.conversations.create', 'communication.messages.create',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($rolePermissions);

            $role->metadata()->updateOrCreate(
                ['role_id' => $role->id],
                [
                    'display_name' => ['en' => ucfirst(str_replace('-', ' ', $roleName))],
                    'description' => ['en' => "System defined {$roleName} role"],
                    'is_system' => true,
                    'is_editable' => false,
                    'is_assignable' => true,
                ]
            );
        }
    }
}
