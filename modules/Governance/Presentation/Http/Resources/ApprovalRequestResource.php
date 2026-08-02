<?php

declare(strict_types=1);

namespace App\Domain\Governance\Http\Resources;

use App\Domain\Auth\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Domain\Governance\Models\ApprovalRequest $resource
 * @mixin \App\Domain\Governance\Models\ApprovalRequest
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
