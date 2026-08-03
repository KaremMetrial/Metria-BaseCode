<?php

declare(strict_types=1);

namespace Modules\Communication\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Communication\Domain\Models\Conversation;

/** @mixin Conversation */
final class ConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Conversation $conversation */
        $conversation = $this->resource;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'state' => $conversation->state->value,
            'title' => $conversation->title,
            'version' => $conversation->version,
            'latest_sequence' => $conversation->next_sequence,
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }
}
