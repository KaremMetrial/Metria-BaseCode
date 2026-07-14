<?php

declare(strict_types=1);

namespace App\Domain\Auth\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\AuthMethodGovernanceService;
use App\Domain\Auth\Services\DynamicSocialiteConfigService;
use App\Domain\Auth\Services\SocialIdentityService;
use App\Domain\Auth\Strategies\SocialProviderStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialAuthController extends ApiController
{
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

        $user = $this->getAuthenticatedUser($request);
        $strategy->verifySocialIdentity($provider, $socialUser, $user->tenant_id);

        $socialService->linkIdentity($user, $provider, $socialUser);

        return $this->respond(message: __('auth.social.linked', ['provider' => $provider]));
    }

    public function unlink(Request $request, string $provider, SocialIdentityService $socialService): JsonResponse
    {
        $socialService->unlinkIdentity($this->getAuthenticatedUser($request), $provider);

        return $this->respond(message: __('auth.social.unlinked', ['provider' => $provider]));
    }

    private function getAuthenticatedUser(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new \App\Core\Exceptions\ApiException(__('auth.unauthorized', ['default' => 'Unauthorized']), status: 401, errorCode: 'unauthorized');
        }

        return $user;
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
