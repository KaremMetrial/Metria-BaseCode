<?php

declare(strict_types=1);

namespace App\Domain\Auth\Pipelines;

use App\Core\Events\EventBus;
use App\Domain\Auth\Events\UserLoggedIn;
use App\Domain\Auth\Events\UserLoggedInByOtp;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Governance\Services\AuditLogger;
use Closure;

class IssueTokenPipe
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EventBus $events
    ) {}

    /**
     * Handle an incoming authentication context and issue API tokens and sessions.
     *
     * @param Closure(AuthContext): mixed $next
     */
    public function handle(AuthContext $context, Closure $next): mixed
    {
        $user = $context->user;

        if (! $user) {
            return $next($context);
        }

        $abilities = [];
        if (method_exists($user, 'hasPermissionTo') && method_exists($user, 'getPermissionsViaRoles')) {
            $abilities = $user->hasPermissionTo('admin.super')
                ? ['*']
                : $user->getPermissionsViaRoles()->pluck('name')->toArray();
        }

        if (empty($abilities)) {
            $abilities = [];
        }

        $token = $user->createToken($context->deviceName, $abilities)->plainTextToken;
        $context->setToken($token);

        if ($context->request->filled('device_token') && method_exists($user, 'updateFcmDeviceToken')) {
            $user->updateFcmDeviceToken(
                $context->request->string('device_token')->value(),
                $context->request->string('device_id')->value() ?: null,
                $context->request->string('device_name')->value() ?: null,
                $context->request->string('platform')->value() ?: null
            );
        }

        if (method_exists($user, 'tokens') && method_exists($user, 'sessions')) {
            if ($tokenModel = $user->tokens()->latest('id')->first()) {
                $user->sessions()->updateOrCreate(
                    ['personal_access_token_id' => $tokenModel->id],
                    [
                        'ip_address' => $context->request->ip() ?: '127.0.0.1',
                        'user_agent' => $context->request->userAgent() ?: 'Unknown',
                        'device_fingerprint' => $context->request->string('device_fingerprint')->value() ?: null,
                        'last_activity_at' => now(),
                    ]
                );
            }
        }

        $this->audit->log('auth.login', $user);

        if ($context->authMethod === 'otp') {
            $this->events->publish(new UserLoggedInByOtp($user, $context->guard));
        } else {
            event(new UserLoggedIn($user));
        }

        $context->payload = [
            'user' => (new UserResource($user->load('roles')))->resolve(),
            'token' => $token,
        ];

        return $next($context);
    }
}
