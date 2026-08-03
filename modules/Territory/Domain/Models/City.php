<?php

declare(strict_types=1);

namespace Modules\Territory\Domain\Models;

use Modules\Shared\Infrastructure\Traits\BelongsToTenant;
use Modules\Shared\Infrastructure\Traits\HasTranslations;
use Modules\Shared\Infrastructure\Traits\HasUuid;
use Modules\Governance\Infrastructure\Traits\Auditable;
use Modules\Shared\Infrastructure\Translation\Traits\AutoTranslates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $governorate_id
 * @property array<string, string> $name
 * @property string|null $postal_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $is_active
 */
class City extends Model
{
    use Auditable;
    use AutoTranslates;
    use BelongsToTenant;
    use HasFactory;
    use HasTranslations;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'governorate_id',
        'name',
        'postal_code',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected array $translatable = ['name'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}
