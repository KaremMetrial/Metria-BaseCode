<?php

declare(strict_types=1);

namespace Modules\Media\Domain\Events;

use Modules\Media\Domain\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaActivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Media $media) {}
}
