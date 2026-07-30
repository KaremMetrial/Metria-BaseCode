<?php

declare(strict_types=1);

namespace Modules\Territory\Domain\Models;

use Modules\Shared\Infrastructure\Traits\BelongsToTenant;
use Modules\Shared\Infrastructure\Traits\HasTranslations;
use Modules\Shared\Infrastructure\Traits\HasUuid;
// TODO: update when Governance module lands
use App\Domain\Governance\Traits\Auditable;
use Modules\Shared\Infrastructure\Translation\Traits\AutoTranslates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
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
        'name',
        'iso_code_2',
        'iso_code_3',
        'phone_code',
        'currency',
        'is_active',
    ];

    protected array $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function governorates(): HasMany
    {
        return $this->hasMany(Governorate::class);
    }
}
