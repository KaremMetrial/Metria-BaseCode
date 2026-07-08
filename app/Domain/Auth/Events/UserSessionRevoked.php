<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;
use App\Domain\Auth\Models\User;

class UserSessionRevoked extends DomainEvent implements StoredInOutbox
{
    public function __construct(
        public readonly User $user,
        public readonly string $sessionId
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'user.session_revoked';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->user->getKey(),
            'email' => $this->user->email,
            'session_id' => $this->sessionId,
        ];
    }
}
