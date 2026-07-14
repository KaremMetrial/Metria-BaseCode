<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation;

class TranslationRegistry
{
    /**
     * @var array<int, class-string>
     */
    protected array $models = [
        \App\Domain\Territory\Models\Zone::class,
        \App\Domain\Territory\Models\Country::class,
        \App\Domain\Territory\Models\Governorate::class,
        \App\Domain\Territory\Models\City::class,
        \App\Domain\Territory\Models\District::class,
        \App\Domain\RBAC\Models\RoleMetadata::class,
    ];

    /**
     * Get all registered translatable domain models.
     *
     * @return array<int, class-string>
     */
    public function all(): array
    {
        return $this->models;
    }

    /**
     * Register an additional translatable model class.
     *
     * @param class-string $modelClass
     */
    public function register(string $modelClass): void
    {
        if (! in_array($modelClass, $this->models, true)) {
            $this->models[] = $modelClass;
        }
    }
}
