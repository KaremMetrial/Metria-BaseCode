<?php

declare(strict_types=1);

namespace Modules\Governance\Presentation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Governance\Domain\Enums\ApprovalStatus;
use Modules\Governance\Domain\Models\ApprovalRequest;
use Modules\Governance\Infrastructure\Services\ApprovalService;
use Modules\Governance\Presentation\Http\Resources\ApprovalRequestResource;
use Modules\Shared\Infrastructure\Support\Pagination;
use Modules\Shared\Presentation\Http\Concerns\RequiresAuthenticatedUser;
use Modules\Shared\Presentation\Http\Controllers\ApiController;

class ApprovalController extends ApiController
{
    use RequiresAuthenticatedUser;

    public function __construct(private readonly ApprovalService $approvals) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ApprovalRequest::class);

        $perPageQuery = $request->query('per_page');

        $requests = ApprovalRequest::query()
            ->with(['requester', 'approver'])
            ->when($request->query('status'), function ($q, $status) {
                $val = is_array($status) ? reset($status) : $status;
                $str = is_scalar($val) ? (string) $val : '';

                return $str !== '' ? $q->where('status', ApprovalStatus::from($str)) : $q;
            })
            ->latest()
            ->paginate(Pagination::resolve(is_numeric($perPageQuery) ? $perPageQuery : null));

        return $this->respond(ApprovalRequestResource::collection($requests));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        Gate::authorize('decide', $approvalRequest);
        $approved = $this->approvals->approve($approvalRequest, $this->authUser($request));

        return $this->respond(new ApprovalRequestResource($approved->load(['requester', 'approver'])), __('governance.approved'));
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        Gate::authorize('decide', $approvalRequest);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $rejected = $this->approvals->reject($approvalRequest, $this->authUser($request), $request->string('reason')->value() ?: null);

        return $this->respond(new ApprovalRequestResource($rejected->load(['requester', 'approver'])), __('governance.rejected'));
    }
}
