<?php

declare(strict_types=1);

namespace App\Domain\Media\Events;

use App\Domain\Media\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaUploaded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Media $media) {}
}
