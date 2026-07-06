<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Core\Support\Money;
use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Balances live in minor units. `held` is the escrowed portion of `balance`
 * (Tarhal-style escrow: buyer funds are held until the trip/delivery
 * completes, then captured to the courier or released back).
 *
 * available = balance - held
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
