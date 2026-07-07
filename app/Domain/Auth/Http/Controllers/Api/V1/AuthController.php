<?php

declare(strict_types=1);

namespace App\Domain\Auth\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Auth\Http\Requests\LoginRequest;
use App\Domain\Auth\Http\Requests\RegisterRequest;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Auth\Services\IssueApiToken;
use App\Domain\Auth\Services\RegisterUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function register(RegisterRequest $request, RegisterUser $register, IssueApiToken $issueToken): JsonResponse
    {
        $user = $register($request->validated());

        ['token' => $token] = $issueToken($user->email, $request->string('password')->value());

        return $this->respondCreated([
            'user' => (new UserResource($user->load('roles')))->resolve(),
            'token' => $token,
        ]);
    }

    public function login(LoginRequest $request, IssueApiToken $issueToken): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $issueToken(
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->string('device_name', 'api')->value(),
        );

        return $this->respond([
            'user' => (new UserResource($user->load('roles')))->resolve(),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->respond(new UserResource($request->user()->load('roles')));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->respondNoContent();
    }
}
