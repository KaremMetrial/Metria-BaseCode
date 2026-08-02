<?php

declare(strict_types=1);

namespace Modules\Wallet\Presentation\Http\Resources;

use Modules\Shared\Domain\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Wallet\Domain\Models\Wallet */
class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'balance' => Money::of($this->balance, $this->currency),
            'held' => Money::of($this->held, $this->currency),
            'available' => $this->available(),
            'currency' => $this->currency,
        ];
    }
}
