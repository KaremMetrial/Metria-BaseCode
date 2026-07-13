<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Exceptions;

use App\Core\Exceptions\DomainException;

class PrivilegeEscalationException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            __('rbac.privilege_escalation_detected'),
            'privilege_escalation_detected',
            403
        );
    }
}
