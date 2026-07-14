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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $city_id
 * @property string $name
 * @property string $code
 * @property array $polygon_coordinates
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property City|null $city
 */
class Zone extends Model
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
            $pi = $polygon[$i] ?? null;
            $pj = $polygon[$j] ?? null;
            if (! is_array($pi) || ! is_array($pj)) {
                continue;
            }

            $xiVal = $pi[0] ?? $pi['lat'] ?? 0;
            $yiVal = $pi[1] ?? $pi['lng'] ?? 0;
            $xjVal = $pj[0] ?? $pj['lat'] ?? 0;
            $yjVal = $pj[1] ?? $pj['lng'] ?? 0;

            $xi = is_numeric($xiVal) ? (float) $xiVal : 0.0;
            $yi = is_numeric($yiVal) ? (float) $yiVal : 0.0;
            $xj = is_numeric($xjVal) ? (float) $xjVal : 0.0;
            $yj = is_numeric($yjVal) ? (float) $yjVal : 0.0;

            $intersect = (($yi > $lng) !== ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / (($yj - $yi) ?: 0.00000001) + $xi);

            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
