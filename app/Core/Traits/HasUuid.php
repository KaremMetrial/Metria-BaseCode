<?php

declare(strict_types=1);

namespace App\Core\Traits;

use Illuminate\Support\Str;

/**
 * UUID primary keys. Add `$table->uuid('id')->primary()` in the migration.
 */
/** @phpstan-ignore trait.unused */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (! $model instanceof \Illuminate\Database\Eloquent\Model) {
                return;
            }
            $keyName = $model->getKeyName();
            if (empty($model->getAttribute($keyName))) {
                $model->setAttribute($keyName, (string) Str::orderedUuid());
            }
        });
    }

    public function initializeHasUuid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
