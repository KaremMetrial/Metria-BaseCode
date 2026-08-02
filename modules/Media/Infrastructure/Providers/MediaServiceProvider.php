<?php

declare(strict_types=1);

namespace Modules\Media\Infrastructure\Providers;

use Modules\Media\Domain\Contracts\ContentModerator;
use Modules\Media\Domain\Contracts\VirusScanner;
use Modules\Media\Domain\Models\Media;
use Modules\Media\Presentation\Policies\MediaPolicy;
use Modules\Media\Infrastructure\Services\ClamAvVirusScanner;
use Modules\Media\Infrastructure\Services\RekognitionModerator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media.php', 'media');

        $this->app->singleton(VirusScanner::class, ClamAvVirusScanner::class);
        $this->app->singleton(ContentModerator::class, RekognitionModerator::class);
    }

    public function boot(): void
    {
        Gate::policy(Media::class, MediaPolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');
    }
}
