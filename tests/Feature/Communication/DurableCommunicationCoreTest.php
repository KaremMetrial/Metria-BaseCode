<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Auth\Domain\Models\User;
use Modules\Communication\Domain\Models\Conversation;
use Modules\Communication\Domain\Models\Message;
use Modules\Shared\Infrastructure\Persistence\Models\OutboxMessage;
use Spatie\Permission\Models\Permission;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class DurableCommunicationCoreTest extends TestCase
{
    use CreatesTenant;
    use CreatesUser;
    use RefreshDatabase;

    public function test_durable_conversation_message_outbox_and_cursor_sync_contract(): void
    {
        $tenantId = $this->setRandomTenant();
        [$author, $recipient] = $this->actors($tenantId);

        $conversationResponse = $this->actingAs($author)->postJson('/api/v1/communication/conversations', [
            'type' => 'direct',
            'participant_ids' => [$recipient->id],
        ], $this->tenantHeaders($tenantId, ['Idempotency-Key' => (string) Str::uuid()]));

        $conversationResponse
            ->assertCreated()
            ->assertJsonPath('data.type', 'direct')
            ->assertJsonPath('data.version', 1);

        $conversationId = (string) $conversationResponse->json('data.id');

        $messageResponse = $this->actingAs($author)->postJson("/api/v1/communication/conversations/{$conversationId}/messages", [
            'client_message_id' => (string) Str::uuid(),
            'kind' => 'text',
            'content' => ['text' => 'Durable message'],
        ], $this->tenantHeaders($tenantId, ['Idempotency-Key' => (string) Str::uuid()]));

        $messageResponse
            ->assertCreated()
            ->assertJsonPath('data.sequence', 1)
            ->assertJsonPath('data.content.text', 'Durable message');

        $this->assertDatabaseHas('communication_conversations', [
            'id' => $conversationId,
            'tenant_id' => $tenantId,
            'next_sequence' => 1,
            'version' => 2,
        ]);
        $this->assertDatabaseCount('communication_memberships', 2);
        $this->assertDatabaseCount('communication_messages', 1);
        $this->assertDatabaseHas('outbox_messages', ['event_name' => 'communication.conversation.created']);
        $this->assertDatabaseHas('outbox_messages', ['event_name' => 'communication.message.created']);

        $sync = $this->actingAs($recipient)->getJson("/api/v1/communication/conversations/{$conversationId}/sync", $this->tenantHeaders($tenantId));

        $sync->assertOk()
            ->assertJsonPath('data.conversation.latest_sequence', 1)
            ->assertJsonPath('data.changes.0.change_type', 'conversation.created')
            ->assertJsonPath('data.changes.1.change_type', 'message.created')
            ->assertJsonPath('data.changes.1.payload.sequence', 1)
            ->assertJsonPath('data.has_more', false);
    }

    public function test_idempotency_replays_canonical_body_and_rejects_different_body_for_same_key(): void
    {
        $tenantId = $this->setRandomTenant();
        [$author, $recipient] = $this->actors($tenantId);
        $key = (string) Str::uuid();

        $body = [
            'type' => 'direct',
            'participant_ids' => [$recipient->id],
        ];

        $first = $this->actingAs($author)->postJson('/api/v1/communication/conversations', $body, $this->tenantHeaders($tenantId, ['Idempotency-Key' => $key]));
        $second = $this->actingAs($author)->postJson('/api/v1/communication/conversations', [
            'participant_ids' => [$recipient->id],
            'type' => 'direct',
        ], $this->tenantHeaders($tenantId, ['Idempotency-Key' => $key]));
        $reused = $this->actingAs($author)->postJson('/api/v1/communication/conversations', [
            'type' => 'private_group',
            'participant_ids' => [$recipient->id],
        ], $this->tenantHeaders($tenantId, ['Idempotency-Key' => $key]));

        $first->assertCreated();
        $second->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $reused->assertStatus(409)->assertJsonPath('error.code', 'idempotency_key_reused');

        $this->assertDatabaseCount('communication_conversations', 1);
    }

    public function test_invalid_message_kind_rolls_back_sequence_message_and_outbox_event(): void
    {
        $tenantId = $this->setRandomTenant();
        [$author, $recipient] = $this->actors($tenantId);
        $conversation = $this->createDirectConversation($author, $recipient);

        $response = $this->actingAs($author)->postJson("/api/v1/communication/conversations/{$conversation->id}/messages", [
            'kind' => 'image',
            'content' => ['attachment_id' => (string) Str::uuid()],
        ], $this->tenantHeaders($tenantId, ['Idempotency-Key' => (string) Str::uuid()]));

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'COMMUNICATION_MESSAGE_KIND_INVALID');

        $conversation->refresh();
        $this->assertSame(0, $conversation->next_sequence);
        $this->assertSame(1, $conversation->version);
        $this->assertSame(0, Message::query()->count());
        $this->assertSame(1, OutboxMessage::query()->where('event_name', 'communication.conversation.created')->count());
        $this->assertSame(0, OutboxMessage::query()->where('event_name', 'communication.message.created')->count());
    }

    public function test_cross_tenant_actor_cannot_synchronize_another_tenants_conversation(): void
    {
        $tenantA = $this->setRandomTenant();
        [$author, $recipient] = $this->actors($tenantA);
        $conversation = $this->createDirectConversation($author, $recipient);

        $tenantB = $this->setRandomTenant();
        [$outsider] = $this->actors($tenantB);

        $response = $this->actingAs($outsider)->getJson("/api/v1/communication/conversations/{$conversation->id}/sync", $this->tenantHeaders($tenantB));

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'COMMUNICATION_CONVERSATION_NOT_FOUND');
    }

    public function test_server_allocates_monotonic_sequences_for_independent_message_commands(): void
    {
        $tenantId = $this->setRandomTenant();
        [$author, $recipient] = $this->actors($tenantId);
        $conversation = $this->createDirectConversation($author, $recipient);

        foreach (['first', 'second'] as $text) {
            $this->actingAs($author)->postJson("/api/v1/communication/conversations/{$conversation->id}/messages", [
                'client_message_id' => (string) Str::uuid(),
                'kind' => 'text',
                'content' => ['text' => $text],
            ], $this->tenantHeaders($tenantId, ['Idempotency-Key' => (string) Str::uuid()]))->assertCreated();
        }

        $sequences = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('sequence')
            ->pluck('sequence')
            ->all();

        $this->assertSame([1, 2], $sequences);
        $conversation->refresh();
        $this->assertSame(2, $conversation->next_sequence);
        $this->assertSame(3, $conversation->version);
    }

    public function test_direct_profile_converges_duplicate_create_commands_on_one_aggregate(): void
    {
        $tenantId = $this->setRandomTenant();
        [$author, $recipient] = $this->actors($tenantId);

        $payload = ['type' => 'direct', 'participant_ids' => [$recipient->id]];
        $first = $this->actingAs($author)->postJson('/api/v1/communication/conversations', $payload, [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $second = $this->actingAs($author)->postJson('/api/v1/communication/conversations', $payload, [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $first->assertCreated();
        $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('communication_conversations', 1);
        $this->assertDatabaseCount('communication_memberships', 2);
        $this->assertDatabaseCount('outbox_messages', 1);
    }

    /** @return array{0: User, 1: User} */
    private function actors(string $tenantId): array
    {
        foreach (['communication.conversations.create', 'communication.messages.create'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $author = $this->createUser($tenantId);
        $author->givePermissionTo(['communication.conversations.create', 'communication.messages.create']);

        return [$author, $this->createUser($tenantId)];
    }

    private function createDirectConversation(User $author, User $recipient): Conversation
    {
        $response = $this->actingAs($author)->postJson('/api/v1/communication/conversations', [
            'type' => 'direct',
            'participant_ids' => [$recipient->id],
        ], $this->tenantHeaders((string) $author->tenant_id, ['Idempotency-Key' => (string) Str::uuid()]));

        $response->assertCreated();

        return Conversation::query()
            ->where('id', (string) $response->json('data.id'))
            ->firstOrFail();
    }

    /** @param array<string, string> $headers
     * @return array<string, string>
     */
    private function tenantHeaders(string $tenantId, array $headers = []): array
    {
        return ['X-Tenant-ID' => $tenantId, ...$headers];
    }
}
