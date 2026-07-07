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

class ApprovalController extends ApiController
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function index(Request $request): JsonResponse
    {
        $requests = ApprovalRequest::query()
            ->with(['requester', 'approver'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', ApprovalStatus::from($status)))
            ->latest()
            ->paginate((int) config('core.api.per_page', 20));

        return $this->respond(ApprovalRequestResource::collection($requests));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $approved = $this->approvals->approve($approvalRequest, $request->user());

        return $this->respond(new ApprovalRequestResource($approved->load(['requester', 'approver'])), __('governance.approved'));
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $rejected = $this->approvals->reject($approvalRequest, $request->user(), $request->input('reason'));

        return $this->respond(new ApprovalRequestResource($rejected->load(['requester', 'approver'])), __('governance.rejected'));
    }
}
