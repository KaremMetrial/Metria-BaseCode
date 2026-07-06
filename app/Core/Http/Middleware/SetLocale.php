<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale resolution order:
 *   1. ?lang= query parameter
 *   2. Authenticated user's stored preference
 *   3. Accept-Language header
 *   4. Fallback locale
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('localization.supported', ['en']);

        $locale = $request->query('lang')
            ?? $request->user()?->locale
            ?? $request->getPreferredLanguage($supported)
            ?? config('localization.fallback', 'en');

        if (! in_array($locale, $supported, true)) {
            $locale = config('localization.fallback', 'en');
        }

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
