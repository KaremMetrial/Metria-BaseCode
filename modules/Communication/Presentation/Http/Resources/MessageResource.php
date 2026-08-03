<?php

declare(strict_types=1);

namespace Modules\Communication\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Communication\Domain\Models\Message;

/** @mixin Message */
final class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Message $message */
        $message = $this->resource;

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'author_actor_id' => $message->author_actor_id,
            'sequence' => $message->sequence,
            'kind' => $message->kind->value,
            'content' => $message->content,
            'state' => $message->state,
            'revision' => $message->revision,
            'created_at' => $message->created_at?->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),
        ];
    }
}
