<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LocaleResolver
{
    /**
     * Resolve the target locale for translation or request processing.
     */
    public function resolveTargetLocale(?string $locale = null, ?Request $request = null): string
    {
        if ($locale !== null && $locale !== '') {
            return $locale;
        }

        if ($request !== null) {
            $headerLocale = $request->header('Accept-Language');
            if ($headerLocale !== null && $headerLocale !== '') {
                return substr($headerLocale, 0, 2);
            }
        }

        $cfgLocale = config('app.locale', 'en');
        return is_string($cfgLocale) ? $cfgLocale : 'en';
    }

    /**
     * Resolve the source (fallback) locale for a model.
     */
    public function resolveSourceLocale(?Model $model = null): string
    {
        $cfgFallback = config('app.fallback_locale', 'en');
        return is_string($cfgFallback) ? $cfgFallback : 'en';
    }
}
