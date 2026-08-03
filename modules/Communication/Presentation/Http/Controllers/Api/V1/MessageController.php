<?php

declare(strict_types=1);

namespace Modules\Communication\Presentation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Auth\Domain\Models\User;
use Modules\Communication\Domain\Models\Conversation;
use Modules\Communication\Domain\Services\CommunicationService;
use Modules\Communication\Presentation\Http\Requests\StoreMessageRequest;
use Modules\Communication\Presentation\Http\Resources\MessageResource;
use Modules\Shared\Application\Exceptions\ApiException;
use Modules\Shared\Presentation\Http\Controllers\ApiController;

final class MessageController extends ApiController
{
    public function __construct(private readonly CommunicationService $communication) {}

    /**
     * Append a durable message with a server-allocated conversation sequence.
     * Retries must use Idempotency-Key.
     * After commit, authorized conversation subscribers receive a
     * communication.message.created change hint; REST remains authoritative.
     *
     * @group Communication
     */
    public function store(StoreMessageRequest $request, string $conversation): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new ApiException('Unauthorized.', 401, 'AUTH_TOKEN_INVALID');
        }

        $target = Conversation::query()->find($conversation);
        if ($target instanceof Conversation) {
            Gate::authorize('sendMessage', $target);
        }

        $content = $request->validated('content');
        $clientMessageId = $request->validated('client_message_id');

        $message = $this->communication->sendMessage(
            $actor,
            $conversation,
            (string) $request->validated('kind'),
            is_array($content) ? $content : [],
            is_string($clientMessageId) ? $clientMessageId : null,
        );

        return $this->respondCreated(new MessageResource($message));
    }
}
