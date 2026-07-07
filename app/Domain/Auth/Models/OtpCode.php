<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
