<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Traits;

use App\Infrastructure\Translation\Jobs\TranslateModelJob;

/** @phpstan-ignore trait.unused */
trait AutoTranslates
{
    /**
     * Boot the trait to hook into the model's saving lifecycle.
     */
    public static function bootAutoTranslates(): void
    {
        static::saved(function ($model) {
            if (! $model instanceof \Illuminate\Database\Eloquent\Model || ! method_exists($model, 'resolveSourceLocale') || ! config('translation.enabled', true)) {
                return;
            }

            // Allow model to opt out of auto-triggering
            if (property_exists($model, 'disableAutoTranslation') && $model->disableAutoTranslation) {
                return;
            }

            // Identify dirty translatable fields
            $dirtyFields = [];
            $translatableFields = property_exists($model, 'translatable') && is_array($model->translatable) ? $model->translatable : [];
            foreach ($translatableFields as $field) {
                if (is_string($field) && $model->isDirty($field)) {
                    $dirtyFields[] = $field;
                }
            }

            if (empty($dirtyFields)) {
                return;
            }

            $locales = config('localization.supported', ['en', 'ar']);
            $supportedLocales = is_array($locales) ? array_filter($locales, 'is_string') : ['en', 'ar'];

            foreach ($supportedLocales as $targetLocale) {
                $fieldsToTranslate = [];
                $resolvedSourceLocale = null;

                foreach ($dirtyFields as $field) {
                    $sourceLocale = $model->resolveSourceLocale($field);
                    if ($sourceLocale === $targetLocale || $sourceLocale === null) {
                        continue;
                    }

                    $existingTarget = null;
                    if (method_exists($model, 'getTranslation')) {
                        /** @phpstan-ignore-next-line */
                        $existingTarget = $model->getTranslation($field, $targetLocale, false);
                    }

                    if (empty($existingTarget)) {
                        $fieldsToTranslate[] = $field;
                        $resolvedSourceLocale = $sourceLocale;
                    }
                }

                if (! empty($fieldsToTranslate) && is_string($resolvedSourceLocale) && $resolvedSourceLocale !== '') {
                    $modelId = $model->getKey();
                    if (is_int($modelId) || is_string($modelId)) {
                        TranslateModelJob::dispatch(
                            static::class,
                            $modelId,
                            $fieldsToTranslate,
                            $resolvedSourceLocale,
                            $targetLocale
                        )->afterCommit();
                    }
                }
            }
        });
    }

    /**
     * Programmatically trigger queueing translations for this model.
     *
     * @param  array|null  $fields  Specific fields to translate, defaults to all translatable fields.
     */
    public function queueTranslations(?array $fields = null, ?string $sourceLocale = null): void
    {
        /** @phpstan-ignore-next-line */
        $fields = $fields ?? (property_exists($this, 'translatable') && is_array($this->translatable) ? $this->translatable : []);
        $locales = config('localization.supported', ['en', 'ar']);
        $supportedLocales = is_array($locales) ? array_filter($locales, 'is_string') : ['en', 'ar'];

        foreach ($supportedLocales as $targetLocale) {
            $fieldsToTranslate = [];
            $resolvedSourceLocale = null;

            foreach ($fields as $field) {
                if (! is_string($field)) {
                    continue;
                }
                $resolved = $sourceLocale ?? $this->resolveSourceLocale($field);
                if ($resolved === $targetLocale || $resolved === null) {
                    continue;
                }

                $existingTarget = null;
                if (method_exists($this, 'getTranslation')) {
                    /** @phpstan-ignore-next-line */
                    $existingTarget = $this->getTranslation($field, $targetLocale, false);
                }

                if (empty($existingTarget)) {
                    $fieldsToTranslate[] = $field;
                    $resolvedSourceLocale = $resolved;
                }
            }

            if (! empty($fieldsToTranslate) && is_string($resolvedSourceLocale) && $resolvedSourceLocale !== '') {
                $modelId = $this->getKey();
                if (is_int($modelId) || is_string($modelId)) {
                    TranslateModelJob::dispatch(
                        static::class,
                        $modelId,
                        $fieldsToTranslate,
                        $resolvedSourceLocale,
                        $targetLocale
                    )->afterCommit();
                }
            }
        }
    }

    /**
     * Deterministically resolve the source locale of a translatable field.
     */
    public function resolveSourceLocale(string $field): ?string
    {
        // 1. Explicit property on model
        if (property_exists($this, 'translationSourceLocale')) {
            $val = $this->translationSourceLocale;
            if (is_string($val) && $val !== '') {
                return (string) $val;
            }
        }

        // 2. Model default locale property
        if (property_exists($this, 'defaultLocale')) {
            $val = $this->defaultLocale;
            if (is_string($val) && $val !== '') {
                return (string) $val;
            }
        }

        // 3. Current app locale if it has a translation populated
        $appLocaleVal = app()->getLocale();
        $appLocale = is_string($appLocaleVal) ? $appLocaleVal : 'en';
        $translationsVal = method_exists($this, 'getTranslations') ? $this->getTranslations($field) : [];
        $translations = is_array($translationsVal) ? $translationsVal : [];
        if (! empty($translations[$appLocale])) {
            return $appLocale;
        }

        // 4. First populated translation in the array
        foreach ($translations as $loc => $val) {
            if (! empty($val)) {
                return (string) $loc;
            }
        }

        // 5. System fallback locale
        $fallback = config('localization.fallback', 'en');
        return is_string($fallback) ? (string) $fallback : 'en';
    }
}
