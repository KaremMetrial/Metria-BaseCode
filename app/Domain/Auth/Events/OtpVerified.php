<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;

class OtpVerified extends DomainEvent implements StoredInOutbox
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $action,
        public readonly string $guard
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'auth.otp_verified';
    }

    public function payload(): array
    {
        return [
            'identifier' => $this->identifier,
            'action' => $this->action,
            'guard' => $this->guard,
        ];
    }
}
