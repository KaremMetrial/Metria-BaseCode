<?php

declare(strict_types=1);

namespace App\Domain\Auth\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Auth\Http\Requests\OtpLoginRequest;
use App\Domain\Auth\Http\Requests\OtpRegisterRequest;
use App\Domain\Auth\Http\Requests\SendOtpRequest;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\LoginWithOtp;
use App\Domain\Auth\Services\RegisterWithOtp;
use App\Domain\Auth\Services\SendOtp;
use Illuminate\Http\JsonResponse;

class OtpAuthController extends ApiController
{
    public function send(SendOtpRequest $request, SendOtp $sendOtp): JsonResponse
    {
        $sendOtp(
            $request->string('identifier')->value(),
            $request->string('action')->value(),
            $request->string('guard', 'web')->value()
        );

        return $this->respond([
            'message' => __('auth.otp_sent', ['default' => 'OTP sent successfully.']),
        ]);
    }

    public function login(OtpLoginRequest $request, LoginWithOtp $loginWithOtp): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $loginWithOtp(
            $request->string('identifier')->value(),
            $request->string('code')->value(),
            $request->string('guard', 'web')->value(),
            $request->string('device_name', 'api')->value()
        );

        if ($request->filled('device_token') && method_exists($user, 'updateFcmDeviceToken')) {
            $user->updateFcmDeviceToken(
                $request->string('device_token')->value(),
                $request->string('device_id')->value() ?: null,
                $request->string('device_name')->value() ?: null,
                $request->string('platform')->value() ?: null
            );
        }

        $userData = ($user instanceof User)
            ? (new UserResource($user->load('roles')))->resolve()
            : $user->toArray();

        return $this->respond([
            'user' => $userData,
            'token' => $token,
        ]);
    }

    public function register(OtpRegisterRequest $request, RegisterWithOtp $registerWithOtp): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $registerWithOtp(
            $request->validated(),
            $request->string('guard', 'web')->value(),
            $request->string('device_name', 'api')->value()
        );

        if ($request->filled('device_token') && method_exists($user, 'updateFcmDeviceToken')) {
            $user->updateFcmDeviceToken(
                $request->string('device_token')->value(),
                $request->string('device_id')->value() ?: null,
                $request->string('device_name')->value() ?: null,
                $request->string('platform')->value() ?: null
            );
        }

        $userData = ($user instanceof User)
            ? (new UserResource($user->load('roles')))->resolve()
            : $user->toArray();

        return $this->respondCreated([
            'user' => $userData,
            'token' => $token,
        ]);
    }
}
