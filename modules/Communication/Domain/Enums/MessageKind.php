<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Enums;

/**
 * Message kinds intentionally implemented by the durable Phase 2A slice.
 * Attachments, cards, threads, reactions and ephemeral kinds arrive later.
 */
enum MessageKind: string
{
    case Text = 'text';
    case Markdown = 'markdown';
    case System = 'system';
}
