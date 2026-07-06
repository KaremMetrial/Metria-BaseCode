<?php

declare(strict_types=1);

namespace App\Domain\Governance\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

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
