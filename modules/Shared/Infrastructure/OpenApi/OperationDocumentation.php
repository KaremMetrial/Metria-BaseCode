<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
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
        $this->documentGenericJsonResponses($operation, $routeInfo);
    }

    /**
     * Scramble cannot infer the payload passed through the shared
     * ApiResponses::respond() helper when an action returns JsonResponse.
     * Without this fallback it emits `type: array, items: {}`, which Swagger
     * UI renders as `[null]`. Preserve inferred schemas where available and
     * document the actual platform envelope for only those unknown responses.
     */
    private function documentGenericJsonResponses(Operation $operation, RouteInfo $routeInfo): void
    {
        foreach ($operation->responses ?? [] as $response) {
            if (! $response instanceof Response || ! in_array((int) $response->code, [200, 201], true)) {
                continue;
            }

            $content = $response->content['application/json'] ?? null;
            if (! $content instanceof Schema || ! $content->type instanceof ArrayType) {
                continue;
            }

            $response->setContent('application/json', Schema::fromType(
                $this->successEnvelope($routeInfo, (int) $response->code)
            ));
        }
    }

    private function successEnvelope(RouteInfo $routeInfo, int $status): ObjectType
    {
        $data = $this->exampleData($routeInfo);
        $dataType = array_is_list($data)
            ? (new ArrayType)->setItems(new ObjectType)
            : new ObjectType;

        return (new ObjectType)
            ->addProperty('success', (new BooleanType)->example(true))
            ->addProperty('message', (new StringType)->nullable(true)->example($status === 201 ? 'Resource created successfully.' : null))
            ->addProperty('data', $dataType)
            ->addProperty('meta', (new ObjectType)
                ->addProperty('request_id', (new StringType)->example('018f68c5-80fd-7b63-b56a-3aecc84f8b62'))
                ->addProperty('locale', (new StringType)->example('en'))
                ->addProperty('direction', (new StringType)->example('ltr')))
            ->setRequired(['success', 'message', 'data', 'meta'])
            ->example([
                'success' => true,
                'message' => $status === 201 ? 'Resource created successfully.' : null,
                'data' => $data,
                'meta' => [
                    'request_id' => '018f68c5-80fd-7b63-b56a-3aecc84f8b62',
                    'locale' => 'en',
                    'direction' => 'ltr',
                ],
            ]);
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    private function exampleData(RouteInfo $routeInfo): array
    {
        $uri = $routeInfo->route->uri();
        $method = strtolower($routeInfo->method);
        $id = '018f68c5-7ddf-7b2f-8b7c-94c705f961b4';
        $timestamp = '2026-08-04T08:00:00+00:00';
        $money = ['amount' => 12500, 'currency' => 'EGP', 'formatted' => '125.00 EGP'];

        return match (true) {
            $uri === 'api/v1/auth/oauth-providers' => $method === 'get' ? [
                'providers' => [[
                    'id' => $id,
                    'provider' => 'google',
                    'client_id' => 'google-client-id',
                    'scopes' => ['openid', 'email', 'profile'],
                    'enabled' => true,
                ]],
            ] : ['provider' => ['id' => $id, 'provider' => 'google', 'client_id' => 'google-client-id', 'scopes' => ['openid', 'email', 'profile'], 'enabled' => true]],
            str_starts_with($uri, 'api/v1/auth/') && str_contains($uri, 'sessions') => [
                'sessions' => [[
                    'id' => $id,
                    'device_name' => 'iPhone 15',
                    'platform' => 'ios',
                    'last_activity_at' => $timestamp,
                ]],
            ],
            str_starts_with($uri, 'api/v1/auth/') => [
                'user' => [
                    'id' => $id,
                    'name' => 'Ada Lovelace',
                    'email' => 'ada@example.test',
                    'phone' => '+201001234567',
                    'locale' => 'en',
                    'roles' => ['customer'],
                    'created_at' => $timestamp,
                ],
                'token' => '1|sanctum_personal_access_token',
            ],
            $uri === 'api/v1/communication/conversations' => $method === 'get' ? [[
                'id' => $id,
                'type' => 'private_group',
                'state' => 'active',
                'title' => 'Delivery team',
                'version' => 1,
                'latest_sequence' => 12,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]] : [
                'id' => $id, 'type' => 'private_group', 'state' => 'active', 'title' => 'Delivery team',
                'version' => 1, 'latest_sequence' => 0, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ],
            str_ends_with($uri, '/sync') => [
                'conversation' => ['id' => $id, 'type' => 'private_group', 'state' => 'active', 'title' => 'Delivery team', 'version' => 1, 'latest_sequence' => 12, 'created_at' => $timestamp, 'updated_at' => $timestamp],
                'changes' => [['version' => 12, 'change_type' => 'message.created', 'message_id' => $id, 'payload' => ['sequence' => 12], 'occurred_at' => $timestamp]],
                'cursor' => 'eyJ2ZXJzaW9uIjoxMn0',
                'has_more' => false,
            ],
            str_ends_with($uri, '/messages') => [
                'id' => $id, 'conversation_id' => $id, 'author_actor_id' => $id, 'sequence' => 12,
                'kind' => 'text', 'content' => ['text' => 'Your order is on the way.'], 'state' => 'active',
                'revision' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ],
            str_starts_with($uri, 'api/v1/payments') => $method === 'get' && $uri === 'api/v1/payments' ? [[
                'id' => $id, 'gateway' => 'stripe', 'gateway_reference' => 'pi_3QExample', 'amount' => $money,
                'status' => 'succeeded', 'description' => 'Order #1042', 'reference_code' => 'ORD-1042', 'paid_at' => $timestamp, 'created_at' => $timestamp,
            ]] : (str_contains($uri, '{payment}') ? [
                'id' => $id, 'gateway' => 'stripe', 'gateway_reference' => 'pi_3QExample', 'amount' => $money,
                'status' => 'succeeded', 'description' => 'Order #1042', 'reference_code' => 'ORD-1042', 'paid_at' => $timestamp, 'created_at' => $timestamp,
            ] : ['payment' => ['id' => $id, 'gateway' => 'stripe', 'amount' => $money, 'status' => 'pending', 'description' => 'Order #1042', 'created_at' => $timestamp], 'next_action' => ['redirect_url' => 'https://checkout.example.test/session/example']]),
            str_starts_with($uri, 'api/v1/wallet/transactions') => [[
                'id' => $id, 'type' => 'credit', 'amount' => $money, 'balance_after' => $money,
                'description' => 'Wallet top-up', 'reference_type' => 'payment', 'reference_id' => $id, 'created_at' => $timestamp,
            ]],
            $uri === 'api/v1/wallet' => ['id' => $id, 'balance' => $money, 'held' => ['amount' => 0, 'currency' => 'EGP', 'formatted' => '0.00 EGP'], 'available' => $money, 'currency' => 'EGP'],
            str_contains($uri, 'territories/countries') => [[
                'id' => $id, 'name' => 'Egypt', 'name_translations' => ['ar' => 'مصر', 'en' => 'Egypt'],
                'iso_code_2' => 'EG', 'iso_code_3' => 'EGY', 'phone_code' => '+20', 'currency' => 'EGP', 'is_active' => true,
            ]],
            str_contains($uri, 'territories/governorates') => [[
                'id' => $id, 'country_id' => $id, 'name' => 'Cairo', 'name_translations' => ['ar' => 'القاهرة', 'en' => 'Cairo'], 'code' => 'C', 'is_active' => true,
            ]],
            str_contains($uri, 'territories/cities') => [[
                'id' => $id, 'governorate_id' => $id, 'name' => 'Nasr City', 'name_translations' => ['ar' => 'مدينة نصر', 'en' => 'Nasr City'], 'postal_code' => '11765', 'latitude' => 30.0626, 'longitude' => 31.3301, 'is_active' => true,
            ]],
            str_contains($uri, 'territories/zones') => [[
                'id' => $id, 'tenant_id' => $id, 'city_id' => $id, 'name' => 'Zone A', 'name_translations' => ['ar' => 'المنطقة أ', 'en' => 'Zone A'], 'code' => 'CAI-A', 'polygon_coordinates' => [], 'is_active' => true,
            ]],
            str_starts_with($uri, 'api/v1/rbac/roles') => $method === 'get' && $uri === 'api/v1/rbac/roles' ? [[
                'id' => $id, 'name' => 'support', 'tenant_id' => $id, 'display_name' => ['en' => 'Support'],
                'description' => ['en' => 'Customer support team'], 'is_system' => false, 'is_editable' => true,
                'is_assignable' => true, 'permissions' => ['tickets.view'], 'created_at' => $timestamp,
            ]] : [
                'id' => $id, 'name' => 'support', 'tenant_id' => $id, 'display_name' => ['en' => 'Support'],
                'description' => ['en' => 'Customer support team'], 'is_system' => false, 'is_editable' => true,
                'is_assignable' => true, 'permissions' => ['tickets.view'], 'created_at' => $timestamp,
            ],
            str_starts_with($uri, 'api/v1/rbac/') => ['permissions' => ['wallet.view', 'payments.view']],
            str_starts_with($uri, 'api/v1/media/') => [
                'id' => $id, 'media_type' => 'image', 'purpose' => 'avatar', 'is_public' => false, 'status' => 'active',
                'filename' => 'avatar.png', 'size' => 48231, 'mime_type' => 'image/png', 'download_url' => 'https://api.example.test/media/'.$id.'/download',
                'moderation_status' => 'approved', 'processing_error' => null, 'custom_properties' => [], 'created_at' => $timestamp, 'activated_at' => $timestamp,
            ],
            str_starts_with($uri, 'api/v1/governance/flags') => ['name' => 'new_checkout', 'enabled' => true],
            str_starts_with($uri, 'api/v1/enums') => ['key' => 'payment_status', 'cases' => [['value' => 'succeeded', 'label' => 'Succeeded']]],
            default => ['status' => 'completed'],
        };
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
