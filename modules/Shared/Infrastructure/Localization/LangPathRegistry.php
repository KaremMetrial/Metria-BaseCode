<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Localization;

/**
 * Registry of module-owned translation directories. Each module registers
 * its own `Infrastructure/Resources/lang` path from its service provider's
 * register(), e.g. `LangPathRegistry::register(__DIR__.'/../Resources/lang')`
 * — this class holds no knowledge of which modules exist, the same
 * self-registration pattern as EnumRegistry/TranslationRegistry.
 *
 * Consumed by CoreServiceProvider, which extends Laravel's default
 * `translation.loader` binding to also search these paths for the plain
 * (non-namespaced) `__('group.key')` syntax every call site already uses —
 * Laravel's Translator only supports path-merging like this for namespaced
 * `package::group.key` translations natively, so this closes that gap
 * without renaming any existing translation key.
 */
class LangPathRegistry
{
    /** @var array<int, string> */
    private static array $paths = [];

    public static function register(string $path): void
    {
        if (! in_array($path, self::$paths, true)) {
            self::$paths[] = $path;
        }
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::$paths;
    }
}
