<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;
use Modules\Auth\Domain\Models\User;

class UserSessionRevoked extends DomainEvent implements StoredInOutbox
{
    public function __construct(
        public readonly User $user,
        public readonly string $sessionId,
        public readonly string|int|null $tokenId = null,
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
            'token_id' => $this->tokenId === null ? null : (string) $this->tokenId,
        ];
    }
}
