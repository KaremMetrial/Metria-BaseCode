<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

enum MediaStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Verifying = 'verifying';
    case Processing = 'processing';
    case Active = 'active';
    case Quarantined = 'quarantined';
    case Failed = 'failed';
    case Deleted = 'deleted';
}
