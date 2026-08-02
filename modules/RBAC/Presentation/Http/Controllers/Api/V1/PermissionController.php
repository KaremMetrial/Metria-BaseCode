<?php

declare(strict_types=1);

namespace Modules\RBAC\Presentation\Http\Controllers\Api\V1;

use Modules\Shared\Presentation\Http\Controllers\ApiController;
use Modules\RBAC\Infrastructure\Support\PermissionRegistry;
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
