<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

final class SanctumSecurityDocumentationStrategy extends MiddlewareAuthSecurityStrategy
{
    public function __construct()
    {
        parent::__construct(
            middleware: ['auth:sanctum'],
            scheme: SecurityScheme::http('bearer', 'Sanctum')
                ->as('SanctumBearer')
                ->setDescription('Laravel Sanctum bearer token.'),
        );
    }
}
