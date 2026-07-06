<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Events;

use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;
use App\Domain\Wallet\Models\Wallet;

class WalletCredited extends DomainEvent implements StoredInOutbox
{
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
}
