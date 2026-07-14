<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Core\Events\EventBus;
use App\Core\Exceptions\DomainException;
use App\Core\Support\Money;
use App\Domain\Auth\Models\User;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Events\WalletCredited;
use App\Domain\Wallet\Events\WalletDebited;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Every mutation:
 *  1. runs inside a DB transaction,
 *  2. re-reads the wallet with a row lock (lockForUpdate) so concurrent
 *     requests serialise instead of double-spending,
 *  3. appends an immutable ledger row with post-operation snapshots.
 *
 * Escrow lifecycle (Tarhal trips/deliveries):
 *   credit → hold (funds locked) → captureHold (paid out) | release (unlocked)
 */
class WalletService
{
    public function __construct(private readonly EventBus $events) {}

    public function firstOrCreateFor(User $user, ?string $currency = null): Wallet
    {
        return Wallet::query()->withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $user->getAttributes()['tenant_id'] ?? null,
                'currency' => strtoupper($currency ?? config('payments.currency', 'EGP')),
                'balance' => 0,
                'held' => 0,
            ],
        );
    }

    public function credit(Wallet $wallet, Money $amount, ?string $description = null, ?Model $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $wallet = $this->locked($wallet);
            $this->assertCurrency($wallet, $amount);

            $wallet->balance += $amount->amount;
            $wallet->save();

            $this->events->publish(new WalletCredited($wallet, $amount->amount));

            return $this->ledger($wallet, WalletTransactionType::Credit, $amount->amount, $description, $reference);
        });
    }

    public function debit(Wallet $wallet, Money $amount, ?string $description = null, ?Model $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $wallet = $this->locked($wallet);
            $this->assertCurrency($wallet, $amount);
            $this->assertAvailable($wallet, $amount);

            $wallet->balance -= $amount->amount;
            $wallet->save();

            $this->events->publish(new WalletDebited($wallet, $amount->amount));

            return $this->ledger($wallet, WalletTransactionType::Debit, $amount->amount, $description, $reference);
        });
    }

    /** Lock part of the available balance (escrow start). */
    public function hold(Wallet $wallet, Money $amount, ?string $description = null, ?Model $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $wallet = $this->locked($wallet);
            $this->assertCurrency($wallet, $amount);
            $this->assertAvailable($wallet, $amount);

            $wallet->held += $amount->amount;
            $wallet->save();

            return $this->ledger($wallet, WalletTransactionType::Hold, $amount->amount, $description, $reference);
        });
    }

    /** Unlock a hold back to the available balance (escrow cancelled). */
    public function release(Wallet $wallet, Money $amount, ?string $description = null, ?Model $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $wallet = $this->locked($wallet);
            $this->assertCurrency($wallet, $amount);
            $this->assertHeld($wallet, $amount);

            $wallet->held -= $amount->amount;
            $wallet->save();

            return $this->ledger($wallet, WalletTransactionType::Release, $amount->amount, $description, $reference);
        });
    }

    /** Held funds leave the wallet for good (escrow settled/paid out). */
    public function captureHold(Wallet $wallet, Money $amount, ?string $description = null, ?Model $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $wallet = $this->locked($wallet);
            $this->assertCurrency($wallet, $amount);
            $this->assertHeld($wallet, $amount);

            return $this->_applyCapture($wallet, $amount, $description, $reference);
        });
    }

    /** Atomic escrow transfer: capture from payer's hold, credit the payee. */
    public function settleHold(Wallet $from, Wallet $to, Money $amount, ?string $description = null, ?Model $reference = null): void
    {
        DB::transaction(function () use ($from, $to, $amount, $description, $reference) {
            // Acquire BOTH locks once, in deterministic key order to prevent deadlocks.
            // Do NOT call captureHold/credit here — they would re-lock the same rows.
            if (strcmp((string) $from->getKey(), (string) $to->getKey()) < 0) {
                $lockedFrom = $this->locked($from);
                $lockedTo = $this->locked($to);
            } else {
                $lockedTo = $this->locked($to);
                $lockedFrom = $this->locked($from);
            }

            $this->assertCurrency($lockedFrom, $amount);
            $this->assertHeld($lockedFrom, $amount);
            $this->assertCurrency($lockedTo, $amount);

            // Operate directly on the already-locked instances (no new lock/tx).
            $this->_applyCapture($lockedFrom, $amount, $description, $reference);
            $this->_applyCredit($lockedTo, $amount, $description, $reference);
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers: operate on a pre-locked wallet, no new transaction/lock.
    // -------------------------------------------------------------------------

    private function _applyCapture(Wallet $wallet, Money $amount, ?string $description, ?Model $reference): WalletTransaction
    {
        $wallet->held -= $amount->amount;
        $wallet->balance -= $amount->amount;
        $wallet->save();

        $this->events->publish(new WalletDebited($wallet, $amount->amount));

        return $this->ledger($wallet, WalletTransactionType::CaptureHold, $amount->amount, $description, $reference);
    }

    private function _applyCredit(Wallet $wallet, Money $amount, ?string $description, ?Model $reference): WalletTransaction
    {
        $wallet->balance += $amount->amount;
        $wallet->save();

        $this->events->publish(new WalletCredited($wallet, $amount->amount));

        return $this->ledger($wallet, WalletTransactionType::Credit, $amount->amount, $description, $reference);
    }

    private function locked(Wallet $wallet): Wallet
    {
        return Wallet::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($wallet->id);
    }

    private function ledger(Wallet $wallet, WalletTransactionType $type, int $amount, ?string $description, ?Model $reference): WalletTransaction
    {
        /** @var WalletTransaction $transaction */
        $transaction = $wallet->transactions()->create([
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'held_after' => $wallet->held,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'description' => $description,
        ]);

        return $transaction;
    }

    private function assertCurrency(Wallet $wallet, Money $amount): void
    {
        if ($wallet->currency !== $amount->currency) {
            throw new DomainException(__('wallets.currency_mismatch'), errorCode: 'wallet_currency_mismatch');
        }
    }

    private function assertAvailable(Wallet $wallet, Money $amount): void
    {
        if ($wallet->availableMinor() < $amount->amount) {
            throw new DomainException(__('wallets.insufficient_funds'), errorCode: 'insufficient_funds');
        }
    }

    private function assertHeld(Wallet $wallet, Money $amount): void
    {
        if ($wallet->held < $amount->amount) {
            throw new DomainException(__('wallets.insufficient_held_funds'), errorCode: 'insufficient_held_funds');
        }
    }
}
