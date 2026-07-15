<?php

declare(strict_types=1);

namespace App\Domain\Governance\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Governance\Enums\ApprovalStatus;
use App\Domain\Governance\Http\Resources\ApprovalRequestResource;
use App\Domain\Governance\Models\ApprovalRequest;
use App\Domain\Governance\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApprovalController extends ApiController
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ApprovalRequest::class);
        $cfgPerPage = config('core.api.per_page', 20);
        $perPage = is_numeric($cfgPerPage) ? (int) $cfgPerPage : 20;

        $requests = ApprovalRequest::query()
            ->with(['requester', 'approver'])
            ->when($request->query('status'), function ($q, $status) {
                $val = is_array($status) ? reset($status) : $status;
                $str = is_scalar($val) ? (string) $val : '';

                return $str !== '' ? $q->where('status', ApprovalStatus::from($str)) : $q;
            })
            ->latest()
            ->paginate($perPage);

        return $this->respond(ApprovalRequestResource::collection($requests));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        Gate::authorize('decide', $approvalRequest);
        $approved = $this->approvals->approve($approvalRequest, $this->getAuthenticatedUser($request));

        return $this->respond(new ApprovalRequestResource($approved->load(['requester', 'approver'])), __('governance.approved'));
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        Gate::authorize('decide', $approvalRequest);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $rejected = $this->approvals->reject($approvalRequest, $this->getAuthenticatedUser($request), $request->string('reason')->value() ?: null);

        return $this->respond(new ApprovalRequestResource($rejected->load(['requester', 'approver'])), __('governance.rejected'));
    }

    private function getAuthenticatedUser(Request $request): \App\Domain\Auth\Models\User
    {
        $user = $request->user();
        if (! $user instanceof \App\Domain\Auth\Models\User) {
            throw new \App\Core\Exceptions\ApiException(__('auth.unauthorized', ['default' => 'Unauthorized']), status: 401, errorCode: 'unauthorized');
        }

        return $user;
    }
}
