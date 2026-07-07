<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Http\Resources;

use App\Core\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
