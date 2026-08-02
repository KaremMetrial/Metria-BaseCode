<?php

declare(strict_types=1);

namespace Modules\Wallet\Presentation\Http\Controllers\Api\V1;

use Modules\Shared\Presentation\Http\Controllers\ApiController;
use Modules\Wallet\Presentation\Http\Resources\WalletResource;
use Modules\Wallet\Presentation\Http\Resources\WalletTransactionResource;
use Modules\Wallet\Infrastructure\Services\WalletService;
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

    private function getAuthenticatedUser(Request $request): \Modules\Auth\Domain\Models\User
    {
        $user = $request->user();
        if (! $user instanceof \Modules\Auth\Domain\Models\User) {
            throw new \Modules\Shared\Application\Exceptions\ApiException(__('auth.unauthorized', ['default' => 'Unauthorized']), status: 401, errorCode: 'unauthorized');
        }

        return $user;
    }
}
