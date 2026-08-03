<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Auth\Domain\Events\AllSessionsRevoked;
use Modules\Auth\Domain\Events\UserSessionRevoked;
use Modules\Communication\Domain\Events\CommunicationDomainEvent;
use Modules\Payment\Domain\Events\PaymentFailed;
use Modules\Payment\Domain\Events\PaymentRefunded;
use Modules\Payment\Domain\Events\PaymentRefundFailed;
use Modules\Payment\Domain\Events\PaymentSucceeded;
use Modules\Shared\Infrastructure\Realtime\RealtimeDomainEventListener;
use Modules\Shared\Infrastructure\Realtime\RealtimeEventMapper;
use Modules\Shared\Infrastructure\Realtime\RealtimePublisherContract;
use Modules\Shared\Infrastructure\Realtime\RedisRealtimePublisher;
use Modules\Wallet\Domain\Events\WalletCredited;
use Modules\Wallet\Domain\Events\WalletDebited;

/** Registers the infrastructure bridge from committed domain facts to Redis. */
class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RealtimeEventMapper::class);
        $this->app->bind(RealtimePublisherContract::class, RedisRealtimePublisher::class);
    }

    public function boot(): void
    {
        foreach ([
            PaymentSucceeded::class,
            PaymentFailed::class,
            PaymentRefunded::class,
            PaymentRefundFailed::class,
            WalletCredited::class,
            WalletDebited::class,
            UserSessionRevoked::class,
            AllSessionsRevoked::class,
            CommunicationDomainEvent::class,
        ] as $event) {
            Event::listen($event, RealtimeDomainEventListener::class);
        }
    }
}
