<?php

declare(strict_types=1);

namespace App\Domain\Auth\Pipelines;

use App\Domain\Auth\Models\User;
use Illuminate\Http\Request;

class AuthContext
{
    public ?User $user = null;

    public ?string $token = null;

    public bool $mfaRequired = false;

    public array $payload = [];

    public function __construct(
        public readonly Request $request,
        public readonly string $deviceName = 'api',
        public readonly ?string $tenantId = null,
        public readonly string $guard = 'web',
        public readonly string $authMethod = 'password'
    ) {}

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function requireMfa(): self
    {
        $this->mfaRequired = true;

        return $this;
    }
}
