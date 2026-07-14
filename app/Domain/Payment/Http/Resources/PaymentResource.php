<?php

declare(strict_types=1);

namespace App\Domain\Payment\Http\Resources;

use App\Core\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Domain\Payment\Models\Payment $resource
 * @mixin \App\Domain\Payment\Models\Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'gateway_reference' => $this->gateway_reference,
            'amount' => Money::of($this->amount, $this->currency),
            'refunded_amount' => $this->when($this->refunded_amount > 0, fn () => Money::of($this->refunded_amount, $this->currency)),
            'status' => $this->status,
            'description' => $this->description,
            'reference_code' => data_get($this->metadata, 'reference_code'),
            'paid_at' => $this->paid_at instanceof \DateTimeInterface ? $this->paid_at->toIso8601String() : $this->paid_at,
            'created_at' => $this->created_at instanceof \DateTimeInterface ? $this->created_at->toIso8601String() : $this->created_at,
        ];
    }
}
