<?php

declare(strict_types=1);

namespace App\Domain\Territory\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasTranslations;
use App\Core\Traits\HasUuid;
use App\Domain\Governance\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use HasTranslations;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'city_id',
        'name',
        'code',
        'polygon_coordinates',
        'is_active',
    ];

    protected array $translatable = ['name'];

    protected $casts = [
        'polygon_coordinates' => 'array',
        'is_active' => 'boolean',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Ray-Casting algorithm to verify if a GPS coordinate (latitude, longitude)
     * falls inside this zone's polygon boundaries.
     * Supports both array tuples [lat, lng] and object formats ['lat' => ..., 'lng' => ...].
     */
    public function containsCoordinate(float $lat, float $lng): bool
    {
        $polygon = $this->polygon_coordinates;
        if (! is_array($polygon) || count($polygon) < 3) {
            return false;
        }

        $inside = false;
        $count = count($polygon);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) ($polygon[$i][0] ?? $polygon[$i]['lat'] ?? 0);
            $yi = (float) ($polygon[$i][1] ?? $polygon[$i]['lng'] ?? 0);
            $xj = (float) ($polygon[$j][0] ?? $polygon[$j]['lat'] ?? 0);
            $yj = (float) ($polygon[$j][1] ?? $polygon[$j]['lng'] ?? 0);

            $intersect = (($yi > $lng) !== ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / (($yj - $yi) ?: 0.00000001) + $xi);

            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
