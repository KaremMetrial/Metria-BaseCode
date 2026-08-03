<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Enums;

/** The deliberately small Phase 2A profile registry. */
enum ConversationType: string
{
    case Direct = 'direct';
    case PrivateGroup = 'private_group';
    case PrivateChannel = 'private_channel';
}
