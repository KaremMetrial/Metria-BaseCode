<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Exceptions;

use Modules\Shared\Application\Exceptions\DomainException;

class RoleNotEditableException extends DomainException
{
    public function __construct(string $roleName)
    {
        parent::__construct(
            __('rbac.role_not_editable', ['role' => $roleName]),
            'role_not_editable',
            status: 403
        );
    }
}
