<?php

declare(strict_types=1);

namespace App\Domain\Auth\Pipelines;

use Closure;

class EnforceMfaPipe
{
    /**
     * Handle an incoming authentication context and enforce MFA requirements.
     *
     * @param  Closure(AuthContext): mixed  $next
     */
    public function handle(AuthContext $context, Closure $next): mixed
    {
        if ($context->user && method_exists($context->user, 'hasMfaEnabled') && $context->user->hasMfaEnabled()) {
            $context->requireMfa();
            $context->payload = [
                'mfa_required' => true,
                'email' => $context->user->email,
                'message' => __('auth.mfa.required'),
            ];

            return $context; // Halt pipeline execution, do not issue Sanctum token
        }

        return $next($context);
    }
}
