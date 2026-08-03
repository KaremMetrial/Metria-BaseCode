<?php

declare(strict_types=1);

namespace Modules\Communication\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Communication\Domain\Models\SyncChange;

/** @mixin SyncChange */
final class SyncChangeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var SyncChange $change */
        $change = $this->resource;

        return [
            'version' => $change->change_version,
            'change_type' => $change->change_type,
            'message_id' => $change->message_id,
            'payload' => $change->payload,
            'occurred_at' => $change->created_at?->toIso8601String(),
        ];
    }
}
