<?php

declare(strict_types=1);

namespace App\Domain\Governance\Services;

use App\Domain\Governance\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * DB-backed, cached runtime settings — the knobs ops/admins can turn without
 * a deploy (commission rates, support phone, maintenance flags, ...).
 */
class SettingsService
{
    private const CACHE_PREFIX = 'settings:';

    private const TTL = 3600;

    public function get(string $key, mixed $default = null): mixed
    {
        $cached = Cache::remember(
            self::CACHE_PREFIX.$key,
            self::TTL,
            function () use ($key) {
                $setting = Setting::query()->where('key', $key)->first();

                return $setting
                    ? ['exists' => true, 'value' => $setting->value['data']]
                    : ['exists' => false, 'value' => null];
            },
        );

        return $cached['exists'] ? $cached['value'] : $default;
    }

    public function set(string $key, mixed $value, ?string $description = null): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['data' => $value], 'description' => $description],
        );

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    public function all(): array
    {
        return Setting::query()
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->value['data'] ?? null])
            ->all();
    }
}
