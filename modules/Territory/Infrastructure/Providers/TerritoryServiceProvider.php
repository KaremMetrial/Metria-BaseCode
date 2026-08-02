<?php

declare(strict_types=1);

namespace Modules\Territory\Infrastructure\Providers;

use Modules\Territory\Domain\Models\City;
use Modules\Territory\Domain\Models\Country;
use Modules\Territory\Domain\Models\District;
use Modules\Territory\Domain\Models\Governorate;
use Modules\Territory\Domain\Models\Zone;
use Modules\Territory\Presentation\Policies\CityPolicy;
use Modules\Territory\Presentation\Policies\CountryPolicy;
use Modules\Territory\Presentation\Policies\DistrictPolicy;
use Modules\Territory\Presentation\Policies\GovernoratePolicy;
use Modules\Territory\Presentation\Policies\ZonePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Infrastructure\Translation\TranslationRegistry;

class TerritoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(Governorate::class, GovernoratePolicy::class);
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(District::class, DistrictPolicy::class);
        Gate::policy(Zone::class, ZonePolicy::class);

        $translationRegistry = $this->app->make(TranslationRegistry::class);
        $translationRegistry->register(Zone::class);
        $translationRegistry->register(Country::class);
        $translationRegistry->register(Governorate::class);
        $translationRegistry->register(City::class);
        $translationRegistry->register(District::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Territory\Infrastructure\Console\Commands\CheckDuplicatesCommand::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');
    }
}