<?php

use App\Domain\Auth\Providers\AuthServiceProvider;
use Modules\Currency\Infrastructure\Providers\CurrencyServiceProvider;
use Modules\Governance\Infrastructure\Providers\GovernanceServiceProvider;
use Modules\Integration\Infrastructure\Providers\IntegrationServiceProvider;
use Modules\Media\Infrastructure\Providers\MediaServiceProvider;
use Modules\Payment\Infrastructure\Providers\PaymentServiceProvider;
use Modules\RBAC\Infrastructure\Providers\RbacAuthServiceProvider;
use Modules\RBAC\Infrastructure\Providers\RbacServiceProvider;
use Modules\Territory\Infrastructure\Providers\TerritoryServiceProvider;
use Modules\Wallet\Infrastructure\Providers\WalletServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\DomainEventServiceProvider;
use Modules\Shared\Infrastructure\Providers\SharedServiceProvider;
use Modules\Webhook\Infrastructure\Providers\WebhookServiceProvider;

return [
    AppServiceProvider::class,
    BroadcastServiceProvider::class,
    DomainEventServiceProvider::class,
    SharedServiceProvider::class,
    CurrencyServiceProvider::class,
    PaymentServiceProvider::class,
    WalletServiceProvider::class,
    WebhookServiceProvider::class,
    IntegrationServiceProvider::class,
    GovernanceServiceProvider::class,
    AuthServiceProvider::class,
    MediaServiceProvider::class,
    TerritoryServiceProvider::class,
    RbacServiceProvider::class,
    RbacAuthServiceProvider::class,
];
