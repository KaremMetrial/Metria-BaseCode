<?php

declare(strict_types=1);

namespace App\Domain\Governance\Services;

use App\Domain\Governance\Models\FeatureFlag;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

/**
 * Feature flags with three activation modes, checked in order:
 *   1. allowlisted user ids   -> on for those users
 *   2. percentage rollout     -> deterministic per user (sticky buckets)
 *   3. global enabled boolean -> on/off for everyone
 */
class FeatureFlagService
{
    public function enabled(string $name, ?Authenticatable $user = null): bool
    {
        $flag = Cache::remember(
            "feature:{$name}",
            300,
            fn () => FeatureFlag::query()->where('name', $name)->first(),
        );

        if (! $flag) {
            return false;
        }

        if (! $flag->enabled) {
            return false;
        }

        if ($user && ! empty($flag->allowed_user_ids)) {
            return in_array($user->getAuthIdentifier(), (array) $flag->allowed_user_ids, true);
        }

        if ($flag->percentage !== null && $flag->percentage < 100) {
            if (! $user) {
                return false;
            }

            $bucket = crc32($name.'|'.$user->getAuthIdentifier()) % 100;

            return $bucket < $flag->percentage;
        }

        return true;
    }

    public function toggle(string $name, bool $enabled): FeatureFlag
    {
        $flag = FeatureFlag::query()->updateOrCreate(['name' => $name], ['enabled' => $enabled]);

        Cache::forget("feature:{$name}");

        return $flag;
    }
}
