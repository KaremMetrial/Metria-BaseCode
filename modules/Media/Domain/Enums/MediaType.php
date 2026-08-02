<?php

declare(strict_types=1);

namespace Modules\Media\Domain\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Archive = 'archive';
}
