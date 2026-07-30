<?php

use App\Domain\Auth\Providers\AuthServiceProvider;
use Modules\Currency\Infrastructure\Providers\CurrencyServiceProvider;
use App\Domain\Governance\Providers\GovernanceServiceProvider;
use App\Domain\Integration\Providers\IntegrationServiceProvider;
use App\Domain\Media\Providers\MediaServiceProvider;
use App\Domain\Payment\Providers\PaymentServiceProvider;
use App\Domain\RBAC\Providers\RbacAuthServiceProvider;
use App\Domain\RBAC\Providers\RbacServiceProvider;
use Modules\Territory\Infrastructure\Providers\TerritoryServiceProvider;
use App\Domain\Wallet\Providers\WalletServiceProvider;
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
