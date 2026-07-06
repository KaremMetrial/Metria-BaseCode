<?php

declare(strict_types=1);

namespace App\Domain\Governance\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Executed = 'executed';
    case Failed = 'failed';
}
