<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Enums;

enum MembershipState: string
{
    case Active = 'active';
    case Left = 'left';
}
