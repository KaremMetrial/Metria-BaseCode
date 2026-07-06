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
            // Users
            'users.view', 'users.create', 'users.update', 'users.delete',
            // Roles
            'roles.view', 'roles.manage',
            // Payments
            'payments.view', 'payments.create', 'payments.refund',
            // Wallets
            'wallets.view', 'wallets.adjust',
            // Territories & Logistics
            'territories.view', 'territories.manage',
            'zones.view', 'zones.manage',
            'couriers.track',
            // Governance
            'governance.settings.view', 'governance.settings.manage',
            'governance.flags.manage',
            'governance.audit.view',
            'governance.approvals.view', 'governance.approvals.decide',
            // Webhooks
            'webhooks.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'super-admin' => $permissions,
            'admin' => array_diff($permissions, ['roles.manage', 'admin.super']),
            'finance' => [
                'payments.view', 'payments.refund',
                'wallets.view', 'wallets.adjust',
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
            'support' => ['users.view', 'payments.view', 'wallets.view', 'territories.view', 'zones.view'],
            'customer' => [],
        ];

        foreach ($roles as $role => $rolePermissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($rolePermissions);
        }
    }
}
