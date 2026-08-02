<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;
use App\Domain\Auth\Models\User;

class AllSessionsRevoked extends DomainEvent implements StoredInOutbox
{
    public function __construct(public readonly User $user)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'user.all_sessions_revoked';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->user->getKey(),
            'email' => $this->user->email,
        ];
    }
}
