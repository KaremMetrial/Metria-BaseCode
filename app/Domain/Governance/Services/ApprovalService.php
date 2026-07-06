<?php

declare(strict_types=1);

namespace App\Domain\Governance\Services;

use App\Core\Exceptions\DomainException;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Enums\ApprovalStatus;
use App\Domain\Governance\Models\ApprovalRequest;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Maker–checker workflow for sensitive operations (refunds, payouts,
 * permission changes...).
 *
 *  1. request()  — a maker records intent + payload; nothing executes.
 *  2. approve()  — a DIFFERENT user with the right permission approves;
 *                  the handler registered in config('governance.approvals
 *                  .handlers') is invoked with the payload.
 *  3. reject()   — closes the request without executing.
 */
class ApprovalService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function request(string $action, array $payload, User $requester): ApprovalRequest
    {
        $this->assertActionRegistered($action);

        $request = ApprovalRequest::query()->create([
            'action' => $action,
            'payload' => $payload,
            'status' => ApprovalStatus::Pending,
            'requested_by' => $requester->getKey(),
        ]);

        $this->audit->log('approval.requested', $request, newValues: ['action' => $action]);

        return $request;
    }

    public function approve(ApprovalRequest $request, User $approver): ApprovalRequest
    {
        $this->assertPending($request);

        if ((string) $request->requested_by === (string) $approver->getKey()) {
            throw new DomainException(__('governance.cannot_approve_own_request'), 'self_approval_forbidden');
        }

        return DB::transaction(function () use ($request, $approver) {
            $request->forceFill([
                'status' => ApprovalStatus::Approved,
                'decided_by' => $approver->getKey(),
                'decided_at' => now(),
            ])->save();

            try {
                $handler = app($this->handlerFor($request->action));
                $handler($request->payload, $request);

                $request->forceFill(['status' => ApprovalStatus::Executed])->save();
            } catch (Throwable $e) {
                report($e);
                $request->forceFill([
                    'status' => ApprovalStatus::Failed,
                    'reason' => mb_substr($e->getMessage(), 0, 500),
                ])->save();
            }

            $this->audit->log('approval.decided', $request, newValues: ['status' => $request->status->value]);

            return $request->refresh();
        });
    }

    public function reject(ApprovalRequest $request, User $approver, ?string $reason = null): ApprovalRequest
    {
        $this->assertPending($request);

        $request->forceFill([
            'status' => ApprovalStatus::Rejected,
            'decided_by' => $approver->getKey(),
            'decided_at' => now(),
            'reason' => $reason,
        ])->save();

        $this->audit->log('approval.rejected', $request, newValues: ['reason' => $reason]);

        return $request;
    }

    private function assertPending(ApprovalRequest $request): void
    {
        if ($request->status !== ApprovalStatus::Pending) {
            throw new DomainException(__('governance.approval_already_decided'), 'approval_not_pending');
        }
    }

    private function assertActionRegistered(string $action): void
    {
        if (! array_key_exists($action, config('governance.approvals.handlers', []))) {
            throw new DomainException("No approval handler registered for [{$action}].", 'unknown_approval_action');
        }
    }

    private function handlerFor(string $action): string
    {
        return config("governance.approvals.handlers.{$action}");
    }
}
