<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Support;

/**
 * Single source of truth for all application capabilities.
 * Permissions are defined here in code, not created dynamically by admins.
 */
class PermissionRegistry
{
    public const array PERMISSIONS = [
        'Governance' => [
            'Audit Logs' => [
                'governance.audit.view' => 'View Audit Logs',
            ],
            'Approvals' => [
                'governance.approvals.view' => 'View Approvals',
                'governance.approvals.decide' => 'Approve or Reject Requests',
            ],
            'Feature Flags' => [
                'governance.flags.manage' => 'Manage Feature Flags',
            ],
        ],
        'RBAC' => [
            'Roles' => [
                'rbac.roles.view' => 'View Roles',
                'rbac.roles.manage' => 'Create, Edit, Delete Roles',
            ],
            'Permissions' => [
                'rbac.permissions.view' => 'View Available Permissions',
                'rbac.permissions.assign' => 'Assign Permissions to Roles or Users',
            ],
        ],
        'Media' => [
            'Files' => [
                'media.upload' => 'Upload Media',
                'media.download' => 'Download Media',
                'media.delete' => 'Delete Media',
            ],
        ],
        'Webhooks' => [
            'Endpoints' => [
                'webhooks.manage' => 'Manage Webhook Endpoints',
            ],
        ],
    ];

    /**
     * Get a flattened list of all permission strings.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $flattened = [];

        foreach (self::PERMISSIONS as $domain => $groups) {
            foreach ($groups as $group => $permissions) {
                foreach (array_keys($permissions) as $permission) {
                    $flattened[] = $permission;
                }
            }
        }

        return $flattened;
    }

    /**
     * Get the structured registry (useful for frontend UI rendering).
     *
     * @return array<string, mixed>
     */
    public static function tree(): array
    {
        return self::PERMISSIONS;
    }
}
