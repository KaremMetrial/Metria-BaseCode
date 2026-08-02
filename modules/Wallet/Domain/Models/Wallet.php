<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Models;

use Modules\Shared\Domain\Support\Money;
use Modules\Shared\Infrastructure\Traits\BelongsToTenant;
use Modules\Shared\Infrastructure\Traits\HasUuid;
use Modules\Auth\Domain\Models\User;
use Modules\Governance\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Balances live in minor units. `held` is the escrowed portion of `balance`
 * (Tarhal-style escrow: buyer funds are held until the trip/delivery
 * completes, then captured to the courier or released back).
 *
 * available = balance - held
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $user_id
 * @property int $balance
 * @property int $held
 * @property string $currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User|null $user
 * @property \Illuminate\Database\Eloquent\Collection<int, WalletTransaction> $transactions
 */
class Wallet extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = ['tenant_id', 'user_id', 'balance', 'held', 'currency'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'held' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WalletTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest('created_at')->latest('id');
    }

    public function availableMinor(): int
    {
        return $this->balance - $this->held;
    }

    public function available(): Money
    {
        return Money::of(max(0, $this->availableMinor()), $this->currency);
    }
}
