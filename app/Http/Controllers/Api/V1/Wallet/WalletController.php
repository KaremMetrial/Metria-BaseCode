<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Wallet\Services\WalletService;
use App\Http\Resources\Wallet\WalletResource;
use App\Http\Resources\Wallet\WalletTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WalletController extends ApiController
{
    public function show(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->firstOrCreateFor($request->user());
        Gate::authorize('view', $wallet);

        return $this->respond(new WalletResource($wallet));
    }

    public function transactions(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->firstOrCreateFor($request->user());
        Gate::authorize('viewTransactions', $wallet);

        $transactions = $wallet->transactions()
            ->with('wallet:id,currency')
            ->paginate(min((int) $request->query('per_page', (string) config('core.api.per_page', 20)), (int) config('core.api.max_per_page', 100)));

        return $this->respond(WalletTransactionResource::collection($transactions));
    }
}
