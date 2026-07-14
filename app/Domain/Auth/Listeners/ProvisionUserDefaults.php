<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Notifications\WelcomeNotification;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Example event-driven side effect: every new user gets a wallet and the
 * default role. Runs on the queue — registration stays fast.
 */
class ProvisionUserDefaults implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public string $queue = 'default';

    public function __construct(private readonly WalletService $wallets) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(1);
    }

    public function handle(object $event): void
    {
        if (! property_exists($event, 'user') || ! ($event->user instanceof \App\Domain\Auth\Models\User)) {
            return;
        }

        $user = $event->user;
        $this->wallets->firstOrCreateFor($user);

        if (! $user->hasAnyRole()) {
            $user->assignRole('customer');
        }

        $user->notify(new WelcomeNotification);
    }
}
