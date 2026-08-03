<?php

declare(strict_types=1);

namespace Modules\Shared\Presentation\Http\Controllers\Internal;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\Domain\Models\User;
use Modules\Payment\Domain\Models\Payment;
use Modules\Shared\Infrastructure\Realtime\RealtimeAssertion;
use Modules\Shared\Infrastructure\Realtime\RealtimeRequestSignature;
use Modules\Shared\Infrastructure\Tenancy\TenantManager;
use Modules\Wallet\Domain\Models\Wallet;

/**
 * Private service-to-service boundary. Browser clients never call these routes:
 * the Node service signs each request and Laravel remains the token/policy owner.
 */
final class RealtimeAuthorizationController
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function authenticate(Request $request): JsonResponse
    {
        if (! $this->isTrustedServiceRequest($request)) {
            return $this->error('SERVICE_UNAUTHORIZED', 401);
        }

        $tokenValue = $request->input('token');
        if (! is_string($tokenValue) || trim($tokenValue) === '') {
            return $this->error('AUTH_TOKEN_MISSING', 401);
        }

        /** @var PersonalAccessToken|null $token */
        $token = PersonalAccessToken::findToken($tokenValue);
        $user = $token?->tokenable;
        if (! $token instanceof PersonalAccessToken || ! $user instanceof User) {
            return $this->error('AUTH_TOKEN_INVALID', 401);
        }
        if ($this->isExpired($token)) {
            return $this->error('AUTH_TOKEN_EXPIRED', 401);
        }
        if (! (bool) $user->is_active) {
            return $this->error('USER_DISABLED', 403);
        }
        if (! is_scalar($user->tenant_id) || (string) $user->tenant_id === '') {
            return $this->error('TENANT_REQUIRED', 403);
        }

        $identity = $this->identityFor($user, (string) $token->getKey());
        $ttl = max(30, (int) config('realtime.session_ttl_seconds', 300));
        $assertion = RealtimeAssertion::issue([
            'aud' => 'metrial-realtime',
            'iat' => now()->timestamp,
            'exp' => now()->addSeconds($ttl)->timestamp,
            ...$identity,
        ]);

        return response()->json(['identity' => $identity, 'assertion' => $assertion, 'expires_at' => now()->addSeconds($ttl)->toIso8601String()]);
    }

    public function authorizeResource(Request $request): JsonResponse
    {
        if (! $this->isTrustedServiceRequest($request)) {
            return $this->error('SERVICE_UNAUTHORIZED', 401);
        }

        $assertion = $request->input('assertion');
        $type = $request->input('resource_type');
        $id = $request->input('resource_id');
        if (! is_string($assertion) || ! is_string($type) || ! is_string($id) || ! preg_match('/^[0-9a-fA-F-]{36}$/', $id)) {
            return $this->error('PAYLOAD_INVALID', 422);
        }

        $claims = RealtimeAssertion::verify($assertion);
        if ($claims === null || ! is_string($claims['sub'] ?? null) || ! is_string($claims['tenant_id'] ?? null) || ! is_string($claims['token_id'] ?? null)) {
            return $this->error('AUTH_TOKEN_EXPIRED', 401);
        }

        /** @var PersonalAccessToken|null $token */
        $token = PersonalAccessToken::query()->find($claims['token_id']);
        if (! $token instanceof PersonalAccessToken || $this->isExpired($token) || (string) $token->getAttribute('tokenable_id') !== $claims['sub']) {
            return $this->error('AUTH_TOKEN_REVOKED', 401);
        }

        /** @var User|null $user */
        $user = User::query()->withoutGlobalScopes()->find($claims['sub']);
        if (! $user instanceof User || ! $user->is_active || (string) $user->tenant_id !== $claims['tenant_id']) {
            return $this->error('AUTH_TOKEN_REVOKED', 401);
        }

        return $this->tenants->runInContext($claims['tenant_id'], function () use ($type, $id, $user, $claims): JsonResponse {
            $resource = match ($type) {
                'payment' => Payment::query()->withoutGlobalScopes()->find($id),
                'wallet' => Wallet::query()->withoutGlobalScopes()->find($id),
                default => null,
            };

            if ($resource === null) {
                return $this->error('RESOURCE_FORBIDDEN', 403);
            }
            if ((string) $resource->tenant_id !== $claims['tenant_id'] || ! Gate::forUser($user)->allows('view', $resource)) {
                return $this->error('RESOURCE_FORBIDDEN', 403);
            }

            return response()->json(['allowed' => true]);
        });
    }

    /** @return array<string, mixed> */
    private function identityFor(User $user, string $tokenId): array
    {
        return $this->tenants->runInContext((string) $user->tenant_id, function () use ($user, $tokenId): array {
            return [
                'sub' => (string) $user->id,
                'tenant_id' => (string) $user->tenant_id,
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                'token_id' => $tokenId,
            ];
        });
    }

    private function isTrustedServiceRequest(Request $request): bool
    {
        $secret = (string) config('realtime.internal_secret', '');
        $timestamp = $request->header('X-Realtime-Timestamp');
        $nonce = $request->header('X-Realtime-Nonce');
        $signature = $request->header('X-Realtime-Signature');
        if ($secret === '' || ! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($nonce) || ! is_string($signature)) {
            return false;
        }
        if (! preg_match('/^[0-9a-f]{64}$/', $signature)) {
            return false;
        }
        if (abs(now()->getTimestamp() - (int) $timestamp) > (int) config('realtime.auth_clock_skew_seconds', 60)) {
            return false;
        }
        try {
            $expected = RealtimeRequestSignature::sign(
                $request->method(),
                $request->getPathInfo(),
                $timestamp,
                $nonce,
                $request->getContent(),
                $secret,
            );
        } catch (\InvalidArgumentException) {
            return false;
        }
        if (! hash_equals($expected, $signature)) {
            return false;
        }

        try {
            return Cache::add('realtime:auth:nonce:'.hash('sha256', $nonce), true, now()->addSeconds(60));
        } catch (\Throwable) {
            return false;
        }
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => ['code' => $code]], $status);
    }

    private function isExpired(PersonalAccessToken $token): bool
    {
        $expiresAt = $token->getAttribute('expires_at');

        return $expiresAt instanceof DateTimeInterface && $expiresAt->getTimestamp() <= now()->getTimestamp();
    }
}
