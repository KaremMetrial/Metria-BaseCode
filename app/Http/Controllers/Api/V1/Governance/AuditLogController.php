<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Governance;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Governance\Models\AuditLog;
use App\Http\Resources\Governance\AuditLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->query('auditable_type'), fn ($q, $type) => $q->where('auditable_type', $type))
            ->latest()
            ->paginate(min((int) $request->query('per_page', (string) config('core.api.per_page', 20)), (int) config('core.api.max_per_page', 100)));

        return $this->respond(AuditLogResource::collection($logs));
    }
}
