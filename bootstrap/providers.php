<?php

use App\Core\Providers\CoreServiceProvider;
use App\Domain\Auth\Providers\AuthServiceProvider;
use App\Domain\Currency\Providers\CurrencyServiceProvider;
use App\Domain\Governance\Providers\GovernanceServiceProvider;
use App\Domain\Integration\Providers\IntegrationServiceProvider;
use App\Domain\Media\Providers\MediaServiceProvider;
use App\Domain\Payment\Providers\PaymentServiceProvider;
use App\Domain\RBAC\Providers\RbacAuthServiceProvider;
use App\Domain\RBAC\Providers\RbacServiceProvider;
use App\Domain\Territory\Providers\TerritoryServiceProvider;
use App\Domain\Wallet\Providers\WalletServiceProvider;
use App\Domain\Webhook\Providers\WebhookServiceProvider;
use App\Infrastructure\Translation\TranslationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\DomainEventServiceProvider;

return [
    AppServiceProvider::class,
    BroadcastServiceProvider::class,
    DomainEventServiceProvider::class,
    TranslationServiceProvider::class,
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
