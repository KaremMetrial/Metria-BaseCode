<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Protects both the documentation UI and its JSON contract. */
final class ApiDocumentationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) config('api_docs.enabled', false);
        $allowed = config('api_docs.allowed_environments', []);

        if (! $enabled || ! in_array(app()->environment(), is_array($allowed) ? $allowed : [], true)) {
            abort(404);
        }

        if ((bool) config('api_docs.public_access', false)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user || (! $user->can('api-docs.view') && ! $user->can('admin.super'))) {
            abort(403);
        }

        return $next($request);
    }
}
