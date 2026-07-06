<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use App\Core\Support\Money;
use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Traits\Auditable;
use App\Domain\Payment\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property PaymentStatus $status
 */
class Payment extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'gateway',
        'gateway_reference',
        'amount',            // minor units
        'refunded_amount',   // minor units
        'currency',
        'status',
        'description',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'metadata' => 'array',
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    public function remainingRefundable(): Money
    {
        return Money::of(max(0, $this->amount - $this->refunded_amount), $this->currency);
    }
}
