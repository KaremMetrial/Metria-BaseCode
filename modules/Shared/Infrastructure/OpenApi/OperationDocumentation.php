<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/** Adds stable, route-derived operation IDs and business-capability tags. */
final class OperationDocumentation implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $route = $routeInfo->route;
        $name = (string) ($route->getName() ?: Str::of($route->uri())->replace('/', '.')->replace(['{', '}'], '')->toString());
        $operation->setOperationId(str_replace(['-', '_'], '.', $name));
        $operation->setTags([$this->tagFor((string) $route->getAction('controller'), $route->uri())]);

        $middleware = $route->gatherMiddleware();
        $details = [];
        if (collect($middleware)->contains(fn (string $m) => str_starts_with($m, 'auth:sanctum'))) {
            $details[] = 'Requires a Laravel Sanctum bearer token.';
        }
        if (collect($middleware)->contains(fn (string $m) => str_contains($m, 'ResolveTenant'))) {
            $details[] = 'Tenant context is resolved from the authenticated user; X-Tenant-ID/X-Tenant cannot switch a non-super-admin user to another tenant.';
        }
        if (collect($middleware)->contains(fn (string $m) => str_starts_with($m, 'permission:'))) {
            $details[] = 'Requires the permission declared by the route middleware.';
        }
        if (collect($middleware)->contains(fn (string $m) => $m === 'idempotent')) {
            $details[] = 'Send Idempotency-Key on retries; identical completed requests replay their stored response, concurrent use returns a conflict, and a key cannot be reused with different canonical request data.';
        }
        if (str_starts_with($route->uri(), 'api/v1/communication/conversations/') && str_ends_with($route->uri(), '/sync')) {
            $details[] = 'This is the authoritative cursor synchronization endpoint; call it after reconnect, realtime:resync_required, or a sequence gap.';
        } elseif (str_starts_with($route->uri(), 'api/v1/communication/') && strtolower($routeInfo->method) === 'post') {
            $details[] = 'After commit, authorized Socket.IO clients may receive only a minimum-safe communication.* change hint. REST and MySQL remain authoritative; clients synchronize with the conversation cursor when needed.';
        }
        $operation->summary($this->summaryFor($routeInfo->method, $routeInfo->methodName() ?: 'operation'));
        $operation->description(implode(' ', $details));
    }

    private function summaryFor(string $httpMethod, string $method): string
    {
        return match (strtolower($httpMethod)) {
            'get' => str_starts_with($method, 'index') ? 'List resources' : 'Retrieve resource details',
            'post' => 'Create or execute this operation',
            'put', 'patch' => 'Update this resource',
            'delete' => 'Delete or revoke this resource',
            default => 'Execute this operation',
        };
    }

    private function tagFor(string $controller, string $uri): string
    {
        return match (true) {
            str_contains($controller, 'Payment') => 'Payments', str_contains($controller, 'Wallet') => 'Wallets',
            str_contains($controller, 'Media') => 'Media', str_contains($controller, 'Auth') || str_contains($controller, 'Otp') || str_contains($controller, 'Social') => 'Authentication',
            str_contains($controller, 'Role') => 'Roles and Permissions', str_contains($controller, 'Governance') || str_contains($uri, 'governance') => 'Governance',
            str_contains($controller, 'Territory') => 'Territories', str_contains($controller, 'Currency') => 'Currencies',
            str_contains($controller, 'Webhook') => 'Webhooks', str_contains($controller, 'Communication') => 'Communication',
            default => 'Platform',
        };
    }
}
