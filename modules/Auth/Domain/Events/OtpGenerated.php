<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;

class OtpGenerated extends DomainEvent implements StoredInOutbox
{
    /**
     * @param  string  $locale  The requester's locale at dispatch time —
     *                          SendOtpNotification (queued, no HTTP request context of its own)
     *                          needs this to send the OTP in the right language; deliberately
     *                          excluded from payload() since it's not part of the public
     *                          webhook contract external consumers rely on.
     */
    public function __construct(
        public readonly string $identifier,
        public readonly string $code,
        public readonly string $action,
        public readonly string $guard,
        public readonly string $locale = 'en',
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'auth.otp_generated';
    }

    public function payload(): array
    {
        return [
            'identifier' => $this->identifier,
            'code' => $this->code,
            'action' => $this->action,
            'guard' => $this->guard,
        ];
    }
}
