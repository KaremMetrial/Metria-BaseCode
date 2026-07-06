<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
