<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Console\Commands;

use App\Core\Outbox\OutboxMessage;
use App\Domain\Webhook\Services\WebhookDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Relay half of the transactional outbox pattern.
 *
 * Scheduled every minute (see routes/console.php). Picks unpublished
 * messages with row-level locks (`FOR UPDATE SKIP LOCKED`) and reserves them
 * before fanning out to external consumers outside the DB transaction.
 */
class PublishOutboxMessages extends Command
{
    protected $signature = 'outbox:publish {--batch=}';

    protected $description = 'Publish pending domain events from the transactional outbox';

    public function handle(WebhookDispatcher $webhooks): int
    {
        $batch = (int) ($this->option('batch') ?: config('governance.outbox.batch_size', 100));
        $maxAttempts = (int) config('governance.outbox.max_attempts', 10);
        $published = 0;
        $workerId = (string) Str::uuid();

        // Stage 1: Atomic Reservation inside short-lived DB transaction
        $messages = DB::transaction(function () use ($batch, $maxAttempts, $workerId) {
            $query = OutboxMessage::query()
                ->whereNull('published_at')
                ->where('attempts', '<', $maxAttempts)
                ->where(function ($q) {
                    $q->whereNull('reserved_at')
                        ->orWhere('reserved_at', '<', now()->subMinutes(10));
                })
                ->orderBy('occurred_at')
                ->limit($batch)
                ->lock('FOR UPDATE SKIP LOCKED');

            $pending = $query->get();

            if ($pending->isEmpty()) {
                return $pending;
            }

            if (config('features.outbox_state_machine', true)) {
                OutboxMessage::query()
                    ->whereIn('id', $pending->pluck('id'))
                    ->update([
                        'reserved_at' => now(),
                        'reserved_by' => $workerId,
                    ]);
            }

            return $pending;
        });

        // Stage 2 & 3: Processing & Relay outside DB transaction
        foreach ($messages as $message) {
            try {
                $this->relay($message, $webhooks);

                // Stage 4: Mark Published
                $message->update([
                    'published_at' => now(),
                    'reserved_at' => null,
                    'reserved_by' => null,
                ]);
                $published++;
            } catch (Throwable $e) {
                report($e);

                $message->update([
                    'attempts' => $message->attempts + 1,
                    'last_error' => mb_substr($e->getMessage(), 0, 500),
                    'reserved_at' => null,
                    'reserved_by' => null,
                ]);
            }
        }

        $this->info("Published {$published} outbox message(s).");

        return self::SUCCESS;
    }

    private function relay(OutboxMessage $message, WebhookDispatcher $webhooks): void
    {
        $webhooks->dispatch($message->event_name, [
            'id' => $message->id,
            'event' => $message->event_name,
            'occurred_at' => $message->occurred_at?->toIso8601String(),
            'data' => $message->payload,
        ]);
    }
}
