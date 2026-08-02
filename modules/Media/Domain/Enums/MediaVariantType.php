<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

enum MediaVariantType: string
{
    case Thumbnail = 'thumbnail';
    case Medium = 'medium';
    case Large = 'large';
    case Webp = 'webp';
    case Avif = 'avif';
    case Mobile = 'mobile';
    case Desktop = 'desktop';
    case Retina = 'retina';
    case Res_1080p = '1080p';
    case Res_720p = '720p';
    case Res_480p = '480p';
    case Original = 'original';
}
