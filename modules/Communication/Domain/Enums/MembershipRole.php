<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
