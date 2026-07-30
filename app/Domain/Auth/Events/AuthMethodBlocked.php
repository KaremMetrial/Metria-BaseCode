<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;

class AuthMethodBlocked extends DomainEvent implements StoredInOutbox
{
    public function __construct(
        public readonly string $method,
        public readonly ?string $identifier = null
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'auth.method_blocked';
    }

    public function payload(): array
    {
        return [
            'method' => $this->method,
            'identifier' => $this->identifier,
        ];
    }
}
