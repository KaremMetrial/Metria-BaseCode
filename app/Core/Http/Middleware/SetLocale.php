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
        $supportedConfig = config('localization.supported', ['en']);
        /** @var array<string> $supported */
        $supported = is_array($supportedConfig) ? array_filter($supportedConfig, 'is_string') : ['en'];

        $langQuery = $request->query('lang');
        $user = $request->user();
        $userLocale = ($user !== null && is_string($loc = $user->getAttribute('locale'))) ? $loc : null;

        $preferred = $request->getPreferredLanguage($supported);

        $fallbackConfig = config('localization.fallback', 'en');
        $fallback = is_string($fallbackConfig) ? $fallbackConfig : 'en';

        $locale = (is_string($langQuery) && $langQuery !== '') ? $langQuery : ($userLocale ?? $preferred ?? $fallback);

        if (! in_array($locale, $supported, true)) {
            $locale = $fallback;
        }

        app()->setLocale($locale);

        $response = $next($request);
        if ($response instanceof Response) {
            $response->headers->set('Content-Language', $locale);
            return $response;
        }

        throw new \UnexpectedValueException('Expected Response instance.');
    }
}
