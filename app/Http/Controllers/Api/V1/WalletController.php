<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Wallet\Services\WalletService;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function show(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->firstOrCreateFor($request->user());

        return $this->respond(new WalletResource($wallet));
    }

    public function transactions(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->firstOrCreateFor($request->user());

        $transactions = $wallet->transactions()
            ->with('wallet:id,currency')
            ->paginate(min((int) $request->query('per_page', (string) config('core.api.per_page', 20)), (int) config('core.api.max_per_page', 100)));

        return $this->respond(WalletTransactionResource::collection($transactions));
    }
}
