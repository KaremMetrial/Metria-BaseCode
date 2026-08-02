<?php

declare(strict_types=1);

namespace Modules\RBAC\Application\Exceptions;

use Modules\Shared\Application\Exceptions\DomainException;

class PrivilegeEscalationException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            __('rbac.privilege_escalation_detected'),
            'privilege_escalation_detected',
            status: 403
        );
    }
}
