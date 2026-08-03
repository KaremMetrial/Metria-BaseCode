<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Enums;

enum ConversationState: string
{
    case Active = 'active';
    case Archived = 'archived';
    case Locked = 'locked';
}
