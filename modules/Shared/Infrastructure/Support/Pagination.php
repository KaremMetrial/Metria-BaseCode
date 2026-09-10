<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Support;

/**
 * Single source of truth for turning a client-requested `per_page` into a
 * safe page size. Every paginated endpoint used to re-derive this from
 * `core.api.per_page`/`max_per_page` by hand (BaseRepository, PaymentController,
 * AuditLogController, WalletController, ...) — same five lines, five places.
 */
final class Pagination
{
    public static function resolve(int|string|null $requested = null): int
    {
        $default = config('core.api.per_page', 20);
        $default = is_numeric($default) ? (int) $default : 20;

        $max = config('core.api.max_per_page', 100);
        $max = is_numeric($max) ? (int) $max : 100;

        $perPage = is_numeric($requested) ? (int) $requested : $default;

        return max(1, min($perPage, $max));
    }
}
