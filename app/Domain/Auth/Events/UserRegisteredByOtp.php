<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;
use Illuminate\Database\Eloquent\Model;

class UserRegisteredByOtp extends DomainEvent implements StoredInOutbox
{
    public function __construct(
        public readonly Model $user,
        public readonly string $guard
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'auth.registered_by_otp';
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
