<?php

declare(strict_types=1);

namespace App\Domain\Governance\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property bool $enabled
 * @property int|null $percentage
 * @property array<int, string|int>|null $allowed_user_ids
 * @property string|null $description
 */
class FeatureFlag extends Model
{
    protected $fillable = ['name', 'enabled', 'percentage', 'allowed_user_ids', 'description'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'percentage' => 'integer',
            'allowed_user_ids' => 'array',
        ];
    }
}
