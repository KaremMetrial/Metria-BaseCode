<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Translation;

/**
 * Registry of translatable domain models. Each owning domain registers its
 * own models from its service provider's boot(), e.g.
 * `TranslationRegistry::register(Zone::class)` — this class holds no
 * knowledge of which domains exist, breaking the Infrastructure<->Domain
 * cycle that previously existed here (this class used to hardcode imports
 * of Territory/RBAC model classes while those models imported AutoTranslates
 * from here).
 */
class TranslationRegistry
{
    /**
     * @var array<int, class-string>
     */
    protected array $models = [];

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
