<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Auth\Models\User;
use Illuminate\Http\JsonResponse;

class EffectivePermissionController extends ApiController
{
    public function show(User $user): JsonResponse
    {
        // Users can always view their own effective permissions.
        // Viewing another user's permissions requires the rbac.permissions.view permission.
        if ($user->id !== request()->user()?->id) {
            $this->authorize('rbac.permissions.view');
        }

        $roles = $user->roles()->pluck('name')->toArray();
        $directPermissions = $user->getDirectPermissions()->pluck('name')->toArray();
        $allPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        // Build the source map
        $sourceMap = [];
        foreach ($user->roles()->with('permissions')->get() as $role) {
            foreach ($role->permissions as $permission) {
                $sourceMap[$permission->name] = "Role: {$role->name}";
            }
        }
        foreach ($directPermissions as $dp) {
            $sourceMap[$dp] = 'Direct Permission';
        }

        return $this->respond([
            'roles' => $roles,
            'direct_permissions' => $directPermissions,
            'effective_permissions' => $allPermissions,
            'source_map' => $sourceMap,
        ]);
    }
}
