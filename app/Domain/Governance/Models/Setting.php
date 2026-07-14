<?php

declare(strict_types=1);

namespace App\Domain\Governance\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|string $id
 * @property string|null $tenant_id
 * @property string $key
 * @property array $value
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'value', 'description'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
