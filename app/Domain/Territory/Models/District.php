<?php

declare(strict_types=1);

namespace App\Domain\Territory\Models;

use App\Core\Traits\HasTranslations;
use App\Core\Traits\HasUuid;
use App\Domain\Governance\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class District extends Model
{
    use Auditable;
    use HasFactory;
    use HasTranslations;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'city_id',
        'name',
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

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
