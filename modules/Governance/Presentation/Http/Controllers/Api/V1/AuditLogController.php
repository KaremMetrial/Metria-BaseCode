<?php

declare(strict_types=1);

namespace Modules\Governance\Presentation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Governance\Domain\Models\AuditLog;
use Modules\Governance\Presentation\Http\Resources\AuditLogResource;
use Modules\Shared\Infrastructure\Support\Pagination;
use Modules\Shared\Presentation\Http\Controllers\ApiController;

class AuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $reqPerPageQuery = $request->query('per_page');

        $logs = AuditLog::query()
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->query('auditable_type'), fn ($q, $type) => $q->where('auditable_type', $type))
            ->latest()
            ->paginate(Pagination::resolve(is_numeric($reqPerPageQuery) ? $reqPerPageQuery : null));

        return $this->respond(AuditLogResource::collection($logs));
    }
}
