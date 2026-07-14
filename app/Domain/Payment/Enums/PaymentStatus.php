<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case ProcessingRefund = 'processing_refund';
    case RefundFailed = 'refund_failed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Refunded, self::RefundFailed, self::Cancelled], true);
    }
}
