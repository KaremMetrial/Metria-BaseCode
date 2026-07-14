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

class Governorate extends Model
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
        'country_id',
        'name',
        'code',
        'is_active',
    ];

    protected array $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
