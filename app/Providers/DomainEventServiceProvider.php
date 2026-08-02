<?php

declare(strict_types=1);

namespace App\Providers;

use Modules\Auth\Domain\Events\UserRegistered;
use Modules\Auth\Infrastructure\Listeners\ProvisionUserDefaults;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * In-process listener wiring. External consumers subscribe through the
 * outbox → webhooks pipeline instead (config: webhook endpoints).
 */
class DomainEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            ProvisionUserDefaults::class,
        ],

        // PaymentSucceeded::class => [FulfillOrder::class, NotifyCustomer::class],
        // PaymentFailed::class    => [AlertOps::class],
    ];
}
