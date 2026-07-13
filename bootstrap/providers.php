<?php

use App\Core\Providers\CoreServiceProvider;
use App\Domain\Auth\Providers\AuthServiceProvider;
use App\Domain\Currency\Providers\CurrencyServiceProvider;
use App\Domain\Governance\Providers\GovernanceServiceProvider;
use App\Domain\Integration\Providers\IntegrationServiceProvider;
use App\Domain\Payment\Providers\PaymentServiceProvider;
use App\Domain\Wallet\Providers\WalletServiceProvider;
use App\Domain\Webhook\Providers\WebhookServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\DomainEventServiceProvider;
use App\Domain\Media\Providers\MediaServiceProvider;
use App\Domain\Territory\Providers\TerritoryServiceProvider;
use App\Domain\RBAC\Providers\RbacServiceProvider;
use App\Domain\RBAC\Providers\RbacAuthServiceProvider;

return [
    AppServiceProvider::class,
    BroadcastServiceProvider::class,
    DomainEventServiceProvider::class,
    CoreServiceProvider::class,
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

