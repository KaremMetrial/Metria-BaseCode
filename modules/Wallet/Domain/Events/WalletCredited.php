<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Events;

use Modules\Shared\Domain\Events\BroadcastableEvent;
use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;
use Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class WalletCredited extends DomainEvent implements ShouldBroadcastNow, StoredInOutbox
{
    use BroadcastableEvent;

    public function __construct(public readonly Wallet $wallet, public readonly int $amount)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'wallet.credited';
    }

    public function payload(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->wallet->user_id,
            'amount' => $this->amount,
            'currency' => $this->wallet->currency,
            'balance' => $this->wallet->balance,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->wallet->user_id),
            new PrivateChannel('wallets.'.$this->wallet->id),
        ];
    }
}
