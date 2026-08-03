<?php

use App\Providers\ApiDocumentationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\DomainEventServiceProvider;
use Modules\Auth\Infrastructure\Providers\AuthServiceProvider;
use Modules\Communication\Infrastructure\Providers\CommunicationServiceProvider;
use Modules\Currency\Infrastructure\Providers\CurrencyServiceProvider;
use Modules\Governance\Infrastructure\Providers\GovernanceServiceProvider;
use Modules\Integration\Infrastructure\Providers\IntegrationServiceProvider;
use Modules\Media\Infrastructure\Providers\MediaServiceProvider;
use Modules\Payment\Infrastructure\Providers\PaymentServiceProvider;
use Modules\RBAC\Infrastructure\Providers\RbacAuthServiceProvider;
use Modules\RBAC\Infrastructure\Providers\RbacServiceProvider;
use Modules\Shared\Infrastructure\Providers\SharedServiceProvider;
use Modules\Territory\Infrastructure\Providers\TerritoryServiceProvider;
use Modules\Wallet\Infrastructure\Providers\WalletServiceProvider;
use Modules\Webhook\Infrastructure\Providers\WebhookServiceProvider;

return [
    AppServiceProvider::class,
    ApiDocumentationServiceProvider::class,
    BroadcastServiceProvider::class,
    DomainEventServiceProvider::class,
    SharedServiceProvider::class,
    CommunicationServiceProvider::class,
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
