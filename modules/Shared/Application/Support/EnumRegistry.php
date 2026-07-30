<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Support;

use BackedEnum;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Registry of enums exposed to API consumers for dropdowns/filters. Each
 * owning domain registers its own enum from its service provider's boot(),
 * e.g. `EnumRegistry::register('payment_status', PaymentStatus::class)` —
 * this class holds no knowledge of which domains exist.
 */
class EnumRegistry
{
    /** @var array<string, class-string<UnitEnum|BackedEnum>> */
    private static array $enums = [];

    /**
     * Register an enum class dynamically (useful for third-party modules or plugins).
     *
     * @param  class-string<UnitEnum|BackedEnum>  $enumClass
     */
    public static function register(string $key, string $enumClass): void
    {
        self::$enums[$key] = $enumClass;
    }

    /**
     * Get all registered enums and their formatted values/labels for API response.
     *
     * @return array<string, list<array{name: string, value: string|int, label: string}>>
     */
    public static function all(): array
    {
        $result = [];

        foreach (self::$enums as $key => $enumClass) {
            $result[$key] = self::formatEnum($enumClass);
        }

        return $result;
    }

    /**
     * Get a specific enum's formatted values/labels by key.
     *
     * @return list<array{name: string, value: string|int, label: string}>|null
     */
    public static function get(string $key): ?array
    {
        $normalizedKey = Str::snake(Str::camel($key));
        $enumClass = self::$enums[$normalizedKey] ?? null;

        if ($enumClass === null) {
            return null;
        }

        return self::formatEnum($enumClass);
    }

    /**
     * Format an enum class into a standardized list of objects.
     *
     * @param  class-string<UnitEnum|BackedEnum>  $enumClass
     * @return list<array{name: string, value: string|int, label: string}>
     */
    private static function formatEnum(string $enumClass): array
    {
        return array_map(function (UnitEnum $case) {
            $value = $case instanceof BackedEnum ? $case->value : $case->name;

            return [
                'name' => $case->name,
                'value' => $value,
                'label' => Str::headline($case->name),
            ];
        }, $enumClass::cases());
    }
}
