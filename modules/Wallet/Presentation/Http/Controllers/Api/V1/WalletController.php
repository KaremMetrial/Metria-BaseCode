<?php

declare(strict_types=1);

namespace Modules\Wallet\Presentation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Shared\Infrastructure\Support\Pagination;
use Modules\Shared\Presentation\Http\Concerns\RequiresAuthenticatedUser;
use Modules\Shared\Presentation\Http\Controllers\ApiController;
use Modules\Wallet\Infrastructure\Services\WalletService;
use Modules\Wallet\Presentation\Http\Resources\WalletResource;
use Modules\Wallet\Presentation\Http\Resources\WalletTransactionResource;

class WalletController extends ApiController
{
    use RequiresAuthenticatedUser;

    public function show(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->firstOrCreateFor($this->authUser($request));
        Gate::authorize('view', $wallet);

        return $this->respond(new WalletResource($wallet));
    }

    public function transactions(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->firstOrCreateFor($this->authUser($request));
        Gate::authorize('viewTransactions', $wallet);

        $perPageVal = $request->query('per_page');

        $transactions = $wallet->transactions()
            ->with('wallet:id,currency')
            ->paginate(Pagination::resolve(is_numeric($perPageVal) ? $perPageVal : null));

        return $this->respond(WalletTransactionResource::collection($transactions));
    }
}
