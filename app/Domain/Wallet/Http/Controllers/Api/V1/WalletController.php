<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Wallet\Http\Resources\WalletResource;
use App\Domain\Wallet\Http\Resources\WalletTransactionResource;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WalletController extends ApiController
{
    public function show(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->firstOrCreateFor($this->getAuthenticatedUser($request));
        Gate::authorize('view', $wallet);

        return $this->respond(new WalletResource($wallet));
    }

    public function transactions(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->firstOrCreateFor($this->getAuthenticatedUser($request));
        Gate::authorize('viewTransactions', $wallet);

        $perPageVal = $request->query('per_page');
        $defaultPerPageVal = config('core.api.per_page', 20);
        $defaultPerPage = is_numeric($defaultPerPageVal) ? (int) $defaultPerPageVal : 20;
        $perPage = is_numeric($perPageVal) ? (int) $perPageVal : $defaultPerPage;

        $maxPerPageVal = config('core.api.max_per_page', 100);
        $maxPerPage = is_numeric($maxPerPageVal) ? (int) $maxPerPageVal : 100;

        $transactions = $wallet->transactions()
            ->with('wallet:id,currency')
            ->paginate(min($perPage, $maxPerPage));

        return $this->respond(WalletTransactionResource::collection($transactions));
    }

    private function getAuthenticatedUser(Request $request): \App\Domain\Auth\Models\User
    {
        $user = $request->user();
        if (! $user instanceof \App\Domain\Auth\Models\User) {
            throw new \App\Core\Exceptions\ApiException(__('auth.unauthorized', ['default' => 'Unauthorized']), status: 401, errorCode: 'unauthorized');
        }

        return $user;
    }
}
