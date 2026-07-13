<?php

declare(strict_types=1);

namespace App\Domain\Auth\Http\Controllers\Api\V1;

use App\Core\Exceptions\ApiException;
use App\Core\Http\Controllers\ApiController;
use App\Domain\Auth\Http\Requests\ConfirmMfaRequest;
use App\Domain\Auth\Http\Requests\DisableMfaRequest;
use App\Domain\Auth\Http\Requests\ForgotPasswordRequest;
use App\Domain\Auth\Http\Requests\LoginRequest;
use App\Domain\Auth\Http\Requests\RegisterRequest;
use App\Domain\Auth\Http\Requests\ResetPasswordRequest;
use App\Domain\Auth\Http\Requests\UpdateFcmTokenRequest;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserSession;
use App\Domain\Auth\Pipelines\AuthContext;
use App\Domain\Auth\Pipelines\AuthPipeline;
use App\Domain\Auth\Services\AuthMethodGovernanceService;
use App\Domain\Auth\Services\IssueApiToken;
use App\Domain\Auth\Services\MfaService;
use App\Domain\Auth\Services\PasswordResetService;
use App\Domain\Auth\Services\RegisterUser;
use App\Domain\Auth\Strategies\PasswordAuthStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthMethodGovernanceService $governance
    ) {
    }

    public function register(RegisterRequest $request, RegisterUser $register, IssueApiToken $issueToken): JsonResponse
    {
        $this->governance->checkMethodEnabled('password');

        // Tenant ID comes from the validated request body only — never from a raw
        // client-controlled header, which would allow registering into any tenant.
        $tenantId = $request->input('tenant_id') ?: null;
        $user = $register($request->validated(), $tenantId);

        ['token' => $token] = $issueToken($user->email, $request->string('password')->value());

        if ($request->filled('device_token')) {
            $user->updateFcmDeviceToken(
                $request->string('device_token')->value(),
                $request->string('device_id')->value() ?: null,
                $request->string('device_name')->value() ?: null,
                $request->string('platform')->value() ?: null
            );
        }

        $this->recordSession($user, $request);

        return $this->respondCreated([
            'user'  => (new UserResource($user->load('roles')))->resolve(),
            'token' => $token,
        ]);
    }

    public function login(
        LoginRequest $request,
        PasswordAuthStrategy $strategy,
        AuthPipeline $pipeline
    ): JsonResponse {
        $this->governance->checkMethodEnabled('password');

        // Tenant resolution happens in ResolveTenant middleware from the authenticated
        // user's own tenant_id — do NOT trust a client-supplied header here.
        $user = $strategy->authenticate([
            'email'    => $request->string('email')->value(),
            'password' => $request->string('password')->value(),
        ], $user->tenant_id ?? null);

        $context = new AuthContext(
            $request,
            $request->string('device_name', 'api')->value(),
            $user->tenant_id ?? null,
            'web'
        );
        $context->setUser($user);

        $context = $pipeline->execute($context);

        return $this->respond($context->payload);
    }

    public function verifyMfa(Request $request, IssueApiToken $issueToken, MfaService $mfa): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $email = $request->string('email')->value();
        $password = $request->string('password')->value();
        $code = $request->string('code')->value();

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            throw new ApiException(__('auth.failed'), status: 401, errorCode: 'invalid_credentials');
        }

        if (! $mfa->verify($user, $code)) {
            throw new ApiException(__('auth.mfa.invalid_code'), status: 401, errorCode: 'invalid_mfa_code');
        }

        ['user' => $user, 'token' => $token] = $issueToken(
            $email,
            $password,
            $request->string('device_name', 'api')->value(),
        );

        if ($request->filled('device_token')) {
            $user->updateFcmDeviceToken(
                $request->string('device_token')->value(),
                $request->string('device_id')->value() ?: null,
                $request->string('device_name')->value() ?: null,
                $request->string('platform')->value() ?: null
            );
        }

        $this->recordSession($user, $request);

        return $this->respond([
            'user' => (new UserResource($user->load('roles')))->resolve(),
            'token' => $token,
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        return $this->respond([
            'sessions' => $request->user()->sessions()->orderByDesc('last_activity_at')->get(),
        ]);
    }

    public function revokeSession(Request $request, string $id): JsonResponse
    {
        /** @var UserSession|null $session */
        $session = $request->user()->sessions()->where('id', $id)->firstOrFail();
        $session->revoke();

        return $this->respond(message: __('auth.session.revoked'));
    }

    public function enableMfa(Request $request, MfaService $mfa): JsonResponse
    {
        return $this->respond($mfa->enable($request->user()));
    }

    public function confirmMfa(ConfirmMfaRequest $request, MfaService $mfa): JsonResponse
    {
        if (! $mfa->confirm($request->user(), $request->string('code')->value())) {
            throw new ApiException(__('auth.mfa.invalid_code'), status: 422, errorCode: 'invalid_mfa_code');
        }

        return $this->respond(message: __('auth.mfa.confirmed'));
    }

    public function disableMfa(DisableMfaRequest $request, MfaService $mfa): JsonResponse
    {
        $mfa->disable($request->user(), $request->string('password')->value());

        return $this->respond(message: __('auth.mfa.disabled'));
    }

    public function forgotPassword(ForgotPasswordRequest $request, PasswordResetService $resetService): JsonResponse
    {
        $resetService->requestReset($request->string('email')->value());

        return $this->respond(message: __('auth.recovery.sent'));
    }

    public function resetPassword(ResetPasswordRequest $request, PasswordResetService $resetService): JsonResponse
    {
        $user = $resetService->reset(
            $request->string('email')->value(),
            $request->string('token')->value(),
            $request->string('password')->value()
        );

        return $this->respond(message: __('auth.recovery.reset_success'));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->respond(new UserResource($request->user()->load('roles')));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        if ($tokenVal = $request->string('device_token')->value()) {
            $request->user()->fcmDeviceTokens()->where('device_token', $tokenVal)->delete();
        }

        return $this->respondNoContent();
    }

    public function updateFcmToken(UpdateFcmTokenRequest $request): JsonResponse
    {
        $request->user()->updateFcmDeviceToken(
            $request->string('device_token')->value(),
            $request->string('device_id')->value() ?: null,
            $request->string('device_name')->value() ?: null,
            $request->string('platform')->value() ?: null
        );

        return $this->respond(message: __('auth.fcm_token_updated', ['default' => 'FCM device token updated successfully.']));
    }

    private function recordSession(User $user, Request $request): void
    {
        if ($tokenModel = $user->tokens()->latest()->first()) {
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
