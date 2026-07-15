<?php

declare(strict_types=1);

namespace App\Core\Traits;

/**
 * JSON-column translations without external packages.
 *
 * Usage:
 *   - migration:  $table->json('name');
 *   - model:      protected array $translatable = ['name', 'description'];
 *
 * Reading $model->name returns the current-locale value with fallback.
 * Writing $model->name = 'x' sets the current-locale value.
 * Use setTranslation()/getTranslations() for explicit locale access.
 */
/** @phpstan-ignore trait.unused */
trait HasTranslations
{
    public function getAttribute($key)
    {
        /** @phpstan-ignore notIdentical.alwaysTrue */
        if ($key !== null && $this->isTranslatableAttribute($key)) {
            return $this->getTranslation($key, app()->getLocale());
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if ($this->isTranslatableAttribute($key) && ! is_array($value)) {
            return $this->setTranslation($key, app()->getLocale(), $value);
        }

        if ($this->isTranslatableAttribute($key) && is_array($value)) {
            $this->attributes[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function isTranslatableAttribute(string $key): bool
    {
        /** @phpstan-ignore-next-line */
        return in_array($key, property_exists($this, 'translatable') ? $this->translatable : [], true);
    }

    public function getTranslation(string $key, string $locale, bool $useFallback = true): mixed
    {
        $translations = $this->getTranslations($key);

        if (array_key_exists($locale, $translations) && $translations[$locale] !== null && $translations[$locale] !== '') {
            return $translations[$locale];
        }

        if ($useFallback) {
            $fallbackVal = config('localization.fallback', 'en');
            $fallback = is_string($fallbackVal) ? $fallbackVal : 'en';

            return $translations[$fallback] ?? array_values($translations)[0] ?? null;
        }

        return null;
    }

    public function getTranslations(string $key): array
    {
        $raw = $this->attributes[$key] ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    public function setTranslation(string $key, string $locale, mixed $value): static
    {
        $translations = $this->getTranslations($key);
        $translations[$locale] = $value;

        $this->attributes[$key] = json_encode($translations, JSON_UNESCAPED_UNICODE);

        return $this;
    }

    public function translationsToArray(): array
    {
        $translatable = property_exists($this, 'translatable') && is_array($this->translatable) ? $this->translatable : [];
        return collect($translatable)
            ->mapWithKeys(function ($key) {
                $keyStr = is_scalar($key) ? (string) $key : '';
                return $keyStr !== '' ? [$keyStr => $this->getTranslations($keyStr)] : [];
            })
            ->all();
    }
}
