<?php

declare(strict_types=1);

namespace Modules\Governance\Presentation\Http\Resources;

use Modules\Auth\Presentation\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \Modules\Governance\Domain\Models\ApprovalRequest $resource
 * @mixin \Modules\Governance\Domain\Models\ApprovalRequest
 */
class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'payload' => $this->payload,
            'status' => $this->status,
            'reason' => $this->reason,
            'requested_by' => $this->whenLoaded('requester', fn () => new UserResource($this->requester)),
            'decided_by' => $this->whenLoaded('approver', fn () => $this->approver ? new UserResource($this->approver) : null),
            'decided_at' => $this->decided_at instanceof \DateTimeInterface ? $this->decided_at->toIso8601String() : $this->decided_at,
            'created_at' => $this->created_at instanceof \DateTimeInterface ? $this->created_at->toIso8601String() : $this->created_at,
        ];
    }
}
