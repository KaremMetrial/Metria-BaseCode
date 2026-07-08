<?php

declare(strict_types=1);

namespace App\Domain\Auth\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Auth\Http\Requests\OtpLoginRequest;
use App\Domain\Auth\Http\Requests\OtpRegisterRequest;
use App\Domain\Auth\Http\Requests\SendOtpRequest;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Pipelines\AuthContext;
use App\Domain\Auth\Pipelines\AuthPipeline;
use App\Domain\Auth\Services\AuthMethodGovernanceService;
use App\Domain\Auth\Services\LoginWithOtp;
use App\Domain\Auth\Services\RegisterWithOtp;
use App\Domain\Auth\Services\SendOtp;
use App\Domain\Auth\Strategies\OtpAuthStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpAuthController extends ApiController
{
    public function __construct(
        private readonly AuthMethodGovernanceService $governance
    ) {
    }

    public function send(SendOtpRequest $request, SendOtp $sendOtp): JsonResponse
    {
        $this->governance->checkMethodEnabled('otp');

        $sendOtp(
            $request->string('identifier')->value(),
            $request->string('action')->value(),
            $request->string('guard', 'web')->value()
        );

        return $this->respond([
            'message' => __('auth.otp_sent', ['default' => 'OTP sent successfully.']),
        ]);
    }

    public function login(
        OtpLoginRequest $request,
        OtpAuthStrategy $strategy,
        AuthPipeline $pipeline
    ): JsonResponse {
        $this->governance->checkMethodEnabled('otp');

        $tenantId = $request->header('X-Tenant-ID') ?: null;
        $user = $strategy->authenticate([
            'identifier' => $request->string('identifier')->value(),
            'code' => $request->string('code')->value(),
            'guard' => $request->string('guard', 'web')->value(),
        ], $tenantId);

        $context = new AuthContext(
            $request,
            $request->string('device_name', 'api')->value(),
            $request->header('X-Tenant-ID') ?: null,
            $request->string('guard', 'web')->value(),
            'otp'
        );
        $context->setUser($user);

        $context = $pipeline->execute($context);

        return $this->respond($context->payload);
    }

    public function register(OtpRegisterRequest $request, RegisterWithOtp $registerWithOtp): JsonResponse
    {
        $this->governance->checkMethodEnabled('otp');

        $tenantId = $request->header('X-Tenant-ID') ?: null;
        ['user' => $user, 'token' => $token] = $registerWithOtp(
            $request->validated(),
            $request->string('guard', 'web')->value(),
            $request->string('device_name', 'api')->value(),
            $tenantId
        );

        if ($request->filled('device_token') && method_exists($user, 'updateFcmDeviceToken')) {
            $user->updateFcmDeviceToken(
                $request->string('device_token')->value(),
                $request->string('device_id')->value() ?: null,
                $request->string('device_name')->value() ?: null,
                $request->string('platform')->value() ?: null
            );
        }

        if ($user instanceof User) {
            $this->recordSession($user, $request);
        }

        $userData = ($user instanceof User)
            ? (new UserResource($user->load('roles')))->resolve()
            : $user->toArray();

        return $this->respondCreated([
            'user' => $userData,
            'token' => $token,
        ]);
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
