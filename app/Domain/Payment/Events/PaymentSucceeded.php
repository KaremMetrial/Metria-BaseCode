<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Core\Events\BroadcastableEvent;
use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;
use App\Domain\Payment\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class PaymentSucceeded extends DomainEvent implements ShouldBroadcastNow, StoredInOutbox
{
    use BroadcastableEvent;

    public function __construct(public readonly Payment $payment)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.succeeded';
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'user_id' => $this->payment->user_id,
            'gateway' => $this->payment->gateway,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
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
            new PrivateChannel('users.'.$this->payment->user_id),
            new PrivateChannel('payments.'.$this->payment->id),
        ];
    }
}
