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

        return (string) config('app.locale', 'en');
    }

    /**
     * Resolve the source (fallback) locale for a model.
     */
    public function resolveSourceLocale(?Model $model = null): string
    {
        return (string) config('app.fallback_locale', 'en');
    }
}
