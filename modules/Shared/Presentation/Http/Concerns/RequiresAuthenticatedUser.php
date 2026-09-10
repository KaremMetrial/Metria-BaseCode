<?php

declare(strict_types=1);

namespace Modules\Shared\Presentation\Http\Concerns;

use Illuminate\Http\Request;
use Modules\Auth\Domain\Models\User;
use Modules\Shared\Application\Exceptions\ApiException;

/**
 * Every route that needs this already sits behind `auth:sanctum`, so
 * `$request->user()` not being a User is unreachable in practice — but
 * several controllers defensively narrowed it anyway, each with its own
 * copy of the same three lines. One place to do it, and to keep the error
 * code the same everywhere it happens.
 */
trait RequiresAuthenticatedUser
{
    protected function authUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new ApiException(__('auth.unauthorized', ['default' => 'Unauthorized']), status: 401, errorCode: 'unauthorized');
        }

        return $user;
    }
}
