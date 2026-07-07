<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Outbox\OutboxMessage;
use App\Domain\Webhook\Services\WebhookDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Relay half of the transactional outbox pattern.
 *
 * Scheduled every minute (see routes/console.php). Picks unpublished
 * messages with row-level locks so multiple workers can run safely, and
 * fans them out to external consumers — here, outgoing webhooks. Extend
 * relay() to also push to Kafka/SNS/other services.
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

        DB::transaction(function () use ($batch, $maxAttempts, $webhooks, &$published) {
            $messages = OutboxMessage::query()
                ->whereNull('published_at')
                ->where('attempts', '<', $maxAttempts)
                ->orderBy('occurred_at')
                ->limit($batch)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            foreach ($messages as $message) {
                try {
                    $this->relay($message, $webhooks);

                    $message->update(['published_at' => now()]);
                    $published++;
                } catch (Throwable $e) {
                    report($e);

                    $message->update([
                        'attempts' => $message->attempts + 1,
                        'last_error' => mb_substr($e->getMessage(), 0, 500),
                    ]);
                }
            }
        });

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
