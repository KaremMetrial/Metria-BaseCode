<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;
use App\Domain\Auth\Models\User;

class UserLoggedInByProvider extends DomainEvent implements StoredInOutbox
{
    public function __construct(
        public readonly User $user,
        public readonly string $provider
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'user.logged_in_by_provider';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->user->getKey(),
            'email' => $this->user->email,
            'provider' => $this->provider,
        ];
    }
}
