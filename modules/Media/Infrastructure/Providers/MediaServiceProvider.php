<?php

declare(strict_types=1);

namespace App\Domain\Media\Providers;

use App\Domain\Media\Contracts\ContentModerator;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Policies\MediaPolicy;
use App\Domain\Media\Services\ClamAvVirusScanner;
use App\Domain\Media\Services\RekognitionModerator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/media.php'), 'media'
        );

        $this->app->singleton(VirusScanner::class, ClamAvVirusScanner::class);
        $this->app->singleton(ContentModerator::class, RekognitionModerator::class);
    }

    public function boot(): void
    {
        Gate::policy(Media::class, MediaPolicy::class);
    }
}
