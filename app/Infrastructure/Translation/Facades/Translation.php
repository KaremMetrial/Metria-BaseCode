<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Facades;

use App\Infrastructure\Translation\TranslationManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Infrastructure\Translation\FluentTranslator from(string $locale)
 * @method static \App\Infrastructure\Translation\FluentTranslator to(string $locale)
 * @method static array translate(array $values)
 * @method static \App\Infrastructure\Translation\Contracts\TranslationProviderInterface driver(string|null $driver = null)
 *
 * @see TranslationManager
 */
class Translation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'translation';
    }
}
