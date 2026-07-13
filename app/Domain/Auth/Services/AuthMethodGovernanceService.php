<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Exceptions\DomainException;
use App\Domain\Governance\Services\SettingsService;

class AuthMethodGovernanceService
{
    public function __construct(
        private readonly SettingsService $settings
    ) {}

    public function isMethodEnabled(string $method): bool
    {
        return (bool) $this->settings->get("auth.methods.{$method}_enabled", true);
    }

    public function checkMethodEnabled(string $method): void
    {
        if (! $this->isMethodEnabled($method)) {
            throw new DomainException(
                __('auth.governance.method_disabled', ['method' => $method]),
                errorCode: 'auth_method_disabled'
            );
        }
    }
}
