<?php

declare(strict_types=1);

namespace App\Domain\Territory\Providers;

use App\Domain\Territory\Models\City;
use App\Domain\Territory\Models\Country;
use App\Domain\Territory\Models\District;
use App\Domain\Territory\Models\Governorate;
use App\Domain\Territory\Models\Zone;
use App\Domain\Territory\Policies\CityPolicy;
use App\Domain\Territory\Policies\CountryPolicy;
use App\Domain\Territory\Policies\DistrictPolicy;
use App\Domain\Territory\Policies\GovernoratePolicy;
use App\Domain\Territory\Policies\ZonePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TerritoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(Governorate::class, GovernoratePolicy::class);
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(District::class, DistrictPolicy::class);
        Gate::policy(Zone::class, ZonePolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Domain\Territory\Console\Commands\CheckDuplicatesCommand::class,
            ]);
        }
    }
}
