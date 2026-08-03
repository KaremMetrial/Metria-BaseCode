<?php

declare(strict_types=1);

use App\Http\Middleware\ApiDocumentationAccess;
use Modules\Shared\Infrastructure\OpenApi\SanctumSecurityDocumentationStrategy;

return [
    'api_path' => ['include' => 'api/v1', 'exclude' => ['api/internal']],
    'api_domain' => null,
    'export_path' => 'artifacts/openapi/api.json',
    'cache' => ['key' => 'scramble.openapi', 'store' => 'file'],
    'info' => [
        'version' => env('API_VERSION', 'v1'),
        'description' => 'Metrial BaseCode is a tenant-aware business platform API for authentication, payments, wallets, media, governance, territories, currencies, integrations, and webhooks.',
    ],
    'ui' => ['title' => 'Metrial BaseCode API'],
    'renderer' => 'elements',
    'middleware' => ['web', ApiDocumentationAccess::class],
    'security_strategy' => SanctumSecurityDocumentationStrategy::class,
];
