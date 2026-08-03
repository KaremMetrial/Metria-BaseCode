<?php

declare(strict_types=1);

namespace Modules\Communication\Presentation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Auth\Domain\Models\User;
use Modules\Communication\Domain\Enums\ConversationType;
use Modules\Communication\Domain\Models\Conversation;
use Modules\Communication\Domain\Services\CommunicationService;
use Modules\Communication\Presentation\Http\Requests\CreateConversationRequest;
use Modules\Communication\Presentation\Http\Resources\ConversationResource;
use Modules\Communication\Presentation\Http\Resources\SyncChangeResource;
use Modules\Shared\Application\Exceptions\ApiException;
use Modules\Shared\Presentation\Http\Controllers\ApiController;

final class ConversationController extends ApiController
{
    public function __construct(private readonly CommunicationService $communication) {}

    /**
     * List the authenticated actor's visible durable conversations.
     *
     * @group Communication
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        Gate::authorize('viewAny', Conversation::class);

        $conversations = Conversation::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('actor_id', $actor->id)
                ->where('state', 'active'))
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return $this->respond(ConversationResource::collection($conversations));
    }

    /**
     * Create a direct, private-group, or private-channel conversation.
     * Retries must use Idempotency-Key.
     * After commit, subscribed clients receive only a minimum-safe
     * communication.conversation.created change hint and must REST-sync.
     *
     * @group Communication
     */
    public function store(CreateConversationRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        Gate::authorize('create', Conversation::class);

        $type = ConversationType::from((string) $request->validated('type'));
        $title = $request->validated('title');
        $participantIds = $request->validated('participant_ids', []);

        $conversation = $this->communication->createConversation(
            $actor,
            $type,
            is_string($title) ? $title : null,
            is_array($participantIds) ? $participantIds : [],
        );

        return $this->respondCreated(new ConversationResource($conversation));
    }

    /**
     * Return an authoritative, cursor-based conversation delta.
     * Call this after reconnect, realtime:resync_required, or a sequence gap.
     *
     * @group Communication
     */
    public function sync(Request $request, string $conversation): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 50), 1), 100);
        $cursor = $request->query('cursor');
        $result = $this->communication->synchronize(
            $this->actor($request),
            $conversation,
            is_string($cursor) ? $cursor : null,
            $limit,
        );

        return $this->respond([
            'conversation' => (new ConversationResource($result['conversation']))->resolve(),
            'changes' => SyncChangeResource::collection($result['changes'])->resolve(),
            'cursor' => $result['cursor'],
            'has_more' => $result['has_more'],
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new ApiException('Unauthorized.', 401, 'AUTH_TOKEN_INVALID');
        }

        return $actor;
    }
}
