<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;
use Modules\Auth\Domain\Models\User;

class PasswordResetSuccessfully extends DomainEvent implements StoredInOutbox
{
    public function __construct(public readonly User $user)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'user.password_reset_successfully';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->user->getKey(),
            'email' => $this->user->email,
        ];
    }
}
