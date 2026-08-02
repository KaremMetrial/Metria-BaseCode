<?php

declare(strict_types=1);

namespace Modules\Payment\Domain\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;
use Modules\Payment\Domain\Models\Payment;

class PaymentFailed extends DomainEvent implements StoredInOutbox
{
    public function __construct(public readonly Payment $payment, public readonly ?string $reason = null)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.failed';
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'user_id' => $this->payment->user_id,
            'gateway' => $this->payment->gateway,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'reason' => $this->reason,
        ];
    }
}
