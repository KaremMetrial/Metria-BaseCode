<?php

use App\Core\Providers\CoreServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DomainEventServiceProvider;

return [
    AppServiceProvider::class,
    DomainEventServiceProvider::class,
    CoreServiceProvider::class,
];
