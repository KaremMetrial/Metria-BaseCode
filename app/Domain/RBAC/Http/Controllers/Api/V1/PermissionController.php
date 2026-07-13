<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\RBAC\Support\PermissionRegistry;
use Illuminate\Http\JsonResponse;

class PermissionController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->authorize('rbac.permissions.view');

        // Return the structured tree (e.g. Domain -> Group -> Key: Value)
        return $this->respond(PermissionRegistry::tree());
    }
}
