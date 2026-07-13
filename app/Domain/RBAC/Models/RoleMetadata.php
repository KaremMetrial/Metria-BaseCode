<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class RoleMetadata extends Model
{
    use HasUuid;
    use HasTranslations;

    protected $table = 'role_metadata';

    public array $translatable = ['display_name', 'description'];

    protected $fillable = [
        'role_id',
        'display_name',
        'description',
        'priority',
        'is_system',
        'is_editable',
        'is_assignable',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_editable' => 'boolean',
            'is_assignable' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
