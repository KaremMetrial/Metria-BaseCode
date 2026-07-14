<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Core\Traits\HasUuid;
use App\Domain\Wallet\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger. Rows are never updated or deleted — the wallet
 * balance is derivable from the ledger, and balance_after makes audits
 * and statement exports O(1).
 *
 * @property string $id
 * @property string $wallet_id
 * @property WalletTransactionType $type
 * @property int $amount
 * @property int $balance_after
 * @property int $held_after
 * @property string|null $reference_type
 * @property string|int|null $reference_id
 * @property string|null $description
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \App\Domain\Wallet\Models\Wallet|null $wallet
 */
class WalletTransaction extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'type',
        'amount',        // minor units, always positive; type carries direction
        'balance_after', // wallet balance snapshot (minor units)
        'held_after',    // wallet held snapshot (minor units)
        'reference_type',
        'reference_id',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'held_after' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
