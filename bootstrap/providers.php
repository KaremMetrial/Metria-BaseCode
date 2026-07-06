<?php

use App\Core\Providers\CoreServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\DomainEventServiceProvider;

return [
    AppServiceProvider::class,
    BroadcastServiceProvider::class,
    DomainEventServiceProvider::class,
    CoreServiceProvider::class,
];
