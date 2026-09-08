<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use Modules\Shared\Application\Exceptions\DomainException;
use Modules\Shared\Domain\Support\Money;
use Modules\Auth\Domain\Models\User;
use Modules\Wallet\Domain\Enums\WalletTransactionType;
use Modules\Wallet\Infrastructure\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WalletService
    {
        return app(WalletService::class);
    }

    public function test_credit_and_debit_write_an_append_only_ledger(): void
    {
        $wallet = $this->service()->firstOrCreateFor(User::factory()->create(), 'EGP');

        $this->service()->credit($wallet, Money::of(10_000, 'EGP'), 'Top-up');
        $this->service()->debit($wallet, Money::of(2_500, 'EGP'), 'Trip fare');

        $wallet->refresh();

        $this->assertSame(7_500, $wallet->balance);
        $this->assertSame(2, $wallet->transactions()->count());
        $this->assertSame(7_500, $wallet->transactions()->first()->balance_after);
    }

    public function test_debit_beyond_available_balance_is_rejected(): void
    {
        $wallet = $this->service()->firstOrCreateFor(User::factory()->create(), 'EGP');
        $this->service()->credit($wallet, Money::of(1_000, 'EGP'));

        $this->expectException(DomainException::class);

        $this->service()->debit($wallet, Money::of(1_001, 'EGP'));
    }

    public function test_escrow_hold_capture_settles_between_wallets(): void
    {
        $payer = $this->service()->firstOrCreateFor(User::factory()->create(), 'EGP');
        $courier = $this->service()->firstOrCreateFor(User::factory()->create(), 'EGP');

        $this->service()->credit($payer, Money::of(5_000, 'EGP'));
        $this->service()->hold($payer, Money::of(3_000, 'EGP'), 'Delivery escrow');

        $payer->refresh();
        $this->assertSame(3_000, $payer->held);
        $this->assertSame(2_000, $payer->availableMinor());

        // Held funds cannot be spent…
        try {
            $this->service()->debit($payer, Money::of(2_500, 'EGP'));
            $this->fail('Expected DomainException for spending held funds.');
        } catch (DomainException) {
            // expected
        }

        // …until the delivery completes and escrow settles to the courier.
        $this->service()->settleHold($payer, $courier, Money::of(3_000, 'EGP'), 'Delivery #1 payout');

        $payer->refresh();
        $courier->refresh();

        $this->assertSame(2_000, $payer->balance);
        $this->assertSame(0, $payer->held);
        $this->assertSame(3_000, $courier->balance);
        $this->assertSame(
            WalletTransactionType::CaptureHold,
            $payer->transactions()->first()->type,
        );
    }

    public function test_release_returns_held_funds_to_available(): void
    {
        $wallet = $this->service()->firstOrCreateFor(User::factory()->create(), 'EGP');
        $this->service()->credit($wallet, Money::of(4_000, 'EGP'));
        $this->service()->hold($wallet, Money::of(4_000, 'EGP'));
        $this->service()->release($wallet, Money::of(4_000, 'EGP'), 'Trip cancelled');

        $wallet->refresh();

        $this->assertSame(4_000, $wallet->balance);
        $this->assertSame(0, $wallet->held);
        $this->assertSame(4_000, $wallet->availableMinor());
    }

    /**
     * Guards the design invariant stated in ARCHITECTURE.md: "concurrent
     * spends serialize — no double-spending under race" (WalletService's
     * lockForUpdate row lock).
     *
     * The test suite runs against sqlite :memory: (phpunit.xml), which is a
     * single connection with no real row-level locking, so genuine parallel
     * requests can't be exercised here. What this test does verify is the
     * observable contract those callers depend on: given a balance that can
     * cover only one of two competing debits, exactly one succeeds, the
     * other is rejected as a DomainException, and the wallet never goes
     * negative — the same assertions a true concurrent test would make on
     * the end state, just without the interleaving. A dedicated
     * multi-connection (MySQL) test belongs in CI alongside this one to
     * cover the interleaving itself.
     */
    public function test_debit_never_overdraws_when_two_requests_compete_for_the_same_balance(): void
    {
        $wallet = $this->service()->firstOrCreateFor(User::factory()->create(), 'EGP');
        $this->service()->credit($wallet, Money::of(1_000, 'EGP'));

        $succeeded = 0;
        $rejected = 0;

        foreach ([Money::of(700, 'EGP'), Money::of(700, 'EGP')] as $attempt) {
            try {
                $this->service()->debit($wallet->fresh(), $attempt, 'Competing spend');
                $succeeded++;
            } catch (DomainException) {
                $rejected++;
            }
        }

        $wallet->refresh();

        $this->assertSame(1, $succeeded, 'Exactly one of the two competing debits should succeed.');
        $this->assertSame(1, $rejected, 'The debit that would overdraw the wallet must be rejected.');
        $this->assertSame(300, $wallet->balance);
        $this->assertGreaterThanOrEqual(0, $wallet->balance, 'Wallet balance must never go negative.');
    }
}
