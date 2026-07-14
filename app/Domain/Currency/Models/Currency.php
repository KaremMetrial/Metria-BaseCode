<?php

declare(strict_types=1);

namespace App\Domain\Currency\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property string $code
 * @property array $name
 * @property array $symbol
 * @property int $minor_units
 * @property bool $is_active
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int, CurrencyExchangeRate> $exchangeRates
 */
class Currency extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'minor_units',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'symbol' => 'array',
            'minor_units' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Currency $currency) {
            // Force upper case ISO code
            $currency->code = strtoupper($currency->code);

            // Enforce single default currency rule application-side
            if ($currency->is_default && $currency->isDirty('is_default')) {
                DB::transaction(function () use ($currency) {
                    // Lock other records to prevent concurrency race conditions
                    DB::table('currencies')
                        ->where('code', '!=', $currency->code)
                        ->lockForUpdate()
                        ->update(['is_default' => false]);
                });
            }
        });

        static::deleting(function (Currency $currency) {
            // Currencies are master data and should never be deleted once referenced
            if ($currency->exchangeRates()->exists()) {
                throw new DomainException(__('currency.cannot_delete_historical', ['currency' => $currency->code]));
            }

            $hasPayments = DB::table('payments')
                ->where('source_currency', $currency->code)
                ->orWhere('target_currency', $currency->code)
                ->orWhere('currency', $currency->code)
                ->exists();

            if ($hasPayments) {
                throw new DomainException(__('currency.cannot_delete_referenced', ['currency' => $currency->code]));
            }
        });
    }

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(CurrencyExchangeRate::class, 'currency_code', 'code');
    }
}
