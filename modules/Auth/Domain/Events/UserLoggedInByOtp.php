<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;
use Illuminate\Database\Eloquent\Model;

class UserLoggedInByOtp extends DomainEvent implements StoredInOutbox
{
    public function __construct(
        public readonly Model $user,
        public readonly string $guard
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'auth.logged_in_by_otp';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->user->getKey(),
            'user_class' => $this->user::class,
            'guard' => $this->guard,
        ];
    }
}
