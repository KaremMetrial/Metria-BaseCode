<?php

declare(strict_types=1);

namespace App\Core\Traits;

use Illuminate\Support\Str;

/**
 * UUID primary keys. Add `$table->uuid('id')->primary()` in the migration.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::orderedUuid();
            }
        });
    }

    public function initializeHasUuid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
