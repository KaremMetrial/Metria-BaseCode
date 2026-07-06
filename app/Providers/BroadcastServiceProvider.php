<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Broadcasting\DualBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::extend('dual', function ($app, $config) {
            return new DualBroadcaster(
                $config['drivers'] ?? ['reverb', 'redis']
            );
        });

        Broadcast::routes([
            'prefix' => 'api/v1',
            'middleware' => ['api', 'auth:sanctum'],
        ]);

        Broadcast::routes([
            'middleware' => ['api', 'auth:sanctum'],
        ]);

        if (file_exists(base_path('routes/channels.php'))) {
            require base_path('routes/channels.php');
        }
    }
}
