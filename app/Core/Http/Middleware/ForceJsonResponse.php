<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API always speaks JSON, regardless of what the client asked for.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);
        if ($response instanceof Response) {
            return $response;
        }

        throw new \UnexpectedValueException('Expected Response instance.');
    }
}
