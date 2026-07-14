<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Http\Resources;

use App\Core\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Wallet\Models\WalletTransaction */
class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->wallet !== null ? $this->wallet->currency : config('payments.currency', 'EGP');

        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => Money::of($this->amount, $currency),
            'balance_after' => Money::of($this->balance_after, $currency),
            'description' => $this->description,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
