<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Models;

use Modules\Shared\Infrastructure\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $identifier
 * @property string $code
 * @property string $guard
 * @property string $action
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OtpCode extends Model
{
    use HasUuid;

    protected $table = 'otps';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'identifier',
        'code',
        'guard',
        'action',
        'attempts',
        'verified_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * Scope a query to only include active (valid, unexpired, unverified) OTP codes.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('verified_at')
            ->where('expires_at', '>', now());
    }
}
