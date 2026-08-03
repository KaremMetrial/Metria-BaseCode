<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Infrastructure\OpenApi\OperationDocumentation;

final class ApiDocumentationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::configure()
            ->routes(fn (Route $route) => $this->isClientRoute($route))
            ->withOperationTransformers(OperationDocumentation::class);

        Scramble::registerApi('admin', ['api_path' => 'api/v1', 'export_path' => 'artifacts/openapi/admin.json'])
            ->expose('docs/admin', 'docs/admin.json')
            ->routes(fn (Route $route) => $this->isApiRoute($route) && $this->hasPermissionMiddleware($route))
            ->withOperationTransformers(OperationDocumentation::class);

        Scramble::registerApi('webhooks', ['api_path' => 'api/v1', 'export_path' => 'artifacts/openapi/webhooks.json'])
            ->expose('docs/webhooks', 'docs/webhooks.json')
            ->routes(fn (Route $route) => $this->isApiRoute($route) && str_contains((string) $route->getAction('controller'), 'Webhook'))
            ->withOperationTransformers(OperationDocumentation::class);
    }

    private function isApiRoute(Route $route): bool
    {
        return str_starts_with($route->uri(), 'api/v1/') && ! str_starts_with($route->uri(), 'api/internal/');
    }

    private function isClientRoute(Route $route): bool
    {
        return $this->isApiRoute($route) && ! $this->hasPermissionMiddleware($route) && ! str_contains((string) $route->getAction('controller'), 'Webhook');
    }

    private function hasPermissionMiddleware(Route $route): bool
    {
        return collect($route->gatherMiddleware())->contains(fn (string $middleware) => str_starts_with($middleware, 'permission:'));
    }
}
