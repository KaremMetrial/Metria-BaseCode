<?php

declare(strict_types=1);

namespace App\Domain\Territory\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasTranslations;
use App\Core\Traits\HasUuid;
use App\Domain\Governance\Traits\Auditable;
use App\Infrastructure\Translation\Traits\AutoTranslates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
