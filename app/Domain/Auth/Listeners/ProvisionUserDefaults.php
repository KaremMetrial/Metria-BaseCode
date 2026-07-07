<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

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

    public function __construct(private readonly WalletService $wallets) {}

    public function handle(object $event): void
    {
        $this->wallets->firstOrCreateFor($event->user);

        if (method_exists($event->user, 'hasAnyRole') && ! $event->user->hasAnyRole()) {
            $event->user->assignRole('customer');
        }
    }
}
