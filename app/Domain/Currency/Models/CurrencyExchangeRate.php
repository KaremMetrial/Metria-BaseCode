<?php

declare(strict_types=1);

namespace App\Domain\Currency\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $currency_code
 * @property string $rate_to_base
 * @property string|null $provider_name
 * @property string|null $provider_version
 * @property string|null $api_schema_version
 * @property string|null $request_id
 * @property string|null $provider_response_hash
 * @property string|null $sync_batch_id
 * @property bool $is_manual
 * @property bool $is_locked
 * @property \Illuminate\Support\Carbon $effective_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property Currency|null $currency
 */
class CurrencyExchangeRate extends Model
{
    use HasUuid;

    protected $fillable = [
        'currency_code',
        'rate_to_base',
        'provider_name',
        'provider_version',
        'api_schema_version',
        'request_id',
        'provider_response_hash',
        'sync_batch_id',
        'is_manual',
        'is_locked',
        'effective_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'rate_to_base' => 'decimal:'.config('currencies.scale', 14),
            'is_manual' => 'boolean',
            'is_locked' => 'boolean',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
