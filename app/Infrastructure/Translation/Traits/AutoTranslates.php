<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Traits;

use App\Infrastructure\Translation\Jobs\TranslateModelJob;

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
            $translatableFields = property_exists($model, 'translatable') ? $model->translatable : [];
            foreach ($translatableFields as $field) {
                if ($model->isDirty($field)) {
                    $dirtyFields[] = $field;
                }
            }

            if (empty($dirtyFields)) {
                return;
            }

            $supportedLocales = config('localization.supported', ['en', 'ar']);

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

                if (! empty($fieldsToTranslate) && $resolvedSourceLocale !== null) {
                    TranslateModelJob::dispatch(
                        static::class,
                        $model->getKey(),
                        $fieldsToTranslate,
                        $resolvedSourceLocale,
                        $targetLocale
                    )->afterCommit();
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
        $fields = $fields ?? (property_exists($this, 'translatable') ? $this->translatable : []);
        $supportedLocales = config('localization.supported', ['en', 'ar']);

        foreach ($supportedLocales as $targetLocale) {
            $fieldsToTranslate = [];
            $resolvedSourceLocale = null;

            foreach ($fields as $field) {
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

            if (! empty($fieldsToTranslate) && $resolvedSourceLocale !== null) {
                TranslateModelJob::dispatch(
                    static::class,
                    $this->getKey(),
                    $fieldsToTranslate,
                    $resolvedSourceLocale,
                    $targetLocale
                )->afterCommit();
            }
        }
    }

    /**
     * Deterministically resolve the source locale of a translatable field.
     */
    public function resolveSourceLocale(string $field): ?string
    {
        // 1. Explicit property on model
        if (property_exists($this, 'translationSourceLocale') && $this->translationSourceLocale !== null) {
            return $this->translationSourceLocale;
        }

        // 2. Model default locale property
        if (property_exists($this, 'defaultLocale') && $this->defaultLocale !== null) {
            return $this->defaultLocale;
        }

        // 3. Current app locale if it has a translation populated
        $appLocale = app()->getLocale();
        /** @phpstan-ignore-next-line */
        $translations = method_exists($this, 'getTranslations') ? $this->getTranslations($field) : [];
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
        return config('localization.fallback', 'en');
    }
}
