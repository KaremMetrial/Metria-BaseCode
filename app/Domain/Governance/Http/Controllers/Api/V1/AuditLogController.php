<?php

declare(strict_types=1);

namespace App\Domain\Governance\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Governance\Http\Resources\AuditLogResource;
use App\Domain\Governance\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);
        $cfgPerPage = config('core.api.per_page', 20);
        $perPageDefault = is_numeric($cfgPerPage) ? (int) $cfgPerPage : 20;
        $cfgMaxPerPage = config('core.api.max_per_page', 100);
        $maxPerPage = is_numeric($cfgMaxPerPage) ? (int) $cfgMaxPerPage : 100;

        $reqPerPageQuery = $request->query('per_page');
        $reqPerPage = is_numeric($reqPerPageQuery) ? (int) $reqPerPageQuery : $perPageDefault;

        $logs = AuditLog::query()
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->query('auditable_type'), fn ($q, $type) => $q->where('auditable_type', $type))
            ->latest()
            ->paginate(min($reqPerPage, $maxPerPage));

        return $this->respond(AuditLogResource::collection($logs));
    }
}
