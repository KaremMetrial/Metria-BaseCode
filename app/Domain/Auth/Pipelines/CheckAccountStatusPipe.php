<?php

declare(strict_types=1);

namespace App\Domain\Auth\Pipelines;

use App\Core\Exceptions\ApiException;
use Closure;

class CheckAccountStatusPipe
{
    /**
     * Handle an incoming authentication context and verify account status.
     *
     * @param  Closure(AuthContext): mixed  $next
     */
    public function handle(AuthContext $context, Closure $next): mixed
    {
        if (! $context->user) {
            throw new ApiException(__('auth.failed'), status: 401, errorCode: 'invalid_credentials');
        }

        // Additional enterprise status checks (e.g., suspension, archival, lockout) can be enforced here.
        if (method_exists($context->user, 'isSuspended') && $context->user->isSuspended()) {
            throw new ApiException(__('auth.login_locked'), status: 403, errorCode: 'account_suspended');
        }

        return $next($context);
    }
}
