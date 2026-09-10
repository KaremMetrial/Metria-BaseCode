<?php

declare(strict_types=1);

namespace Modules\Auth\Presentation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Domain\Models\User;
use Modules\Auth\Infrastructure\Services\AuthMethodGovernanceService;
use Modules\Auth\Infrastructure\Services\DynamicSocialiteConfigService;
use Modules\Auth\Infrastructure\Services\SocialIdentityService;
use Modules\Auth\Infrastructure\Strategies\SocialProviderStrategy;
use Modules\Auth\Presentation\Http\Resources\UserResource;
use Modules\Shared\Presentation\Http\Concerns\RequiresAuthenticatedUser;
use Modules\Shared\Presentation\Http\Controllers\ApiController;

class SocialAuthController extends ApiController
{
    use RequiresAuthenticatedUser;

    public function __construct(private readonly AuthMethodGovernanceService $governance) {}

    public function redirect(
        Request $request,
        string $provider,
        DynamicSocialiteConfigService $configService,
        SocialProviderStrategy $strategy
    ): JsonResponse {
        $this->governance->checkMethodEnabled('social');

        $tenantId = $request->header('X-Tenant-ID') ?: null;
        $configService->configure($provider, $tenantId);

        $url = $strategy->generateRedirectUrl($provider);

        return $this->respond(['url' => $url]);
    }

    public function callback(Request $request, string $provider, SocialIdentityService $socialService, DynamicSocialiteConfigService $configService, SocialProviderStrategy $strategy): JsonResponse
    {
        $this->governance->checkMethodEnabled('social');

        $tenantId = $request->header('X-Tenant-ID') ?: null;
        $configService->configure($provider, $tenantId);

        $request->validate([
            'id' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'name' => ['nullable', 'string'],
            'token' => ['nullable', 'string'],
        ]);

        $socialUser = [
            'id' => $request->string('id')->value(),
            'email' => $request->string('email')->value() ?: null,
            'name' => $request->string('name')->value() ?: 'User',
            'token' => $request->string('token')->value() ?: null,
        ];

        $strategy->verifySocialIdentity($provider, $socialUser, $tenantId);

        ['user' => $user, 'token' => $token, 'is_new' => $isNew] = $socialService->loginOrRegister(
            $provider,
            $socialUser,
            $tenantId,
            $request->string('device_name', 'social')->value()
        );

        $this->recordSession($user, $request);

        return $this->respond([
            'user' => (new UserResource($user->load('roles')))->resolve(),
            'token' => $token,
            'is_new' => $isNew,
        ]);
    }

    public function link(Request $request, string $provider, SocialIdentityService $socialService, SocialProviderStrategy $strategy): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'name' => ['nullable', 'string'],
            'token' => ['nullable', 'string'],
        ]);

        $socialUser = [
            'id' => $request->string('id')->value(),
            'email' => $request->string('email')->value() ?: null,
            'name' => $request->string('name')->value() ?: 'User',
            'token' => $request->string('token')->value() ?: null,
        ];

        $user = $this->authUser($request);
        $strategy->verifySocialIdentity($provider, $socialUser, $user->tenant_id);

        $socialService->linkIdentity($user, $provider, $socialUser);

        return $this->respond(message: __('auth.social.linked', ['provider' => $provider]));
    }

    public function unlink(Request $request, string $provider, SocialIdentityService $socialService): JsonResponse
    {
        $socialService->unlinkIdentity($this->authUser($request), $provider);

        return $this->respond(message: __('auth.social.unlinked', ['provider' => $provider]));
    }

    private function recordSession(User $user, Request $request): void
    {
        if ($tokenModel = $user->tokens()->latest('id')->first()) {
            $user->sessions()->updateOrCreate(
                ['personal_access_token_id' => $tokenModel->id],
                [
                    'ip_address' => $request->ip() ?: '127.0.0.1',
                    'user_agent' => $request->userAgent() ?: 'Unknown',
                    'device_fingerprint' => $request->string('device_fingerprint')->value() ?: null,
                    'last_activity_at' => now(),
                ]
            );
        }
    }
}
