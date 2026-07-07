<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Core\Exceptions\DomainException;
use App\Core\Support\Money;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Enums\ApprovalStatus;
use App\Domain\Governance\Models\ApprovalRequest;
use App\Domain\Governance\Services\ApprovalService;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConcurrencyAndRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_request_double_execution_is_prevented(): void
    {
        Http::fake([
            'api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_test_123',
                'status' => 'succeeded',
            ]),
        ]);

        $maker = User::factory()->create();
        $approver = User::factory()->create();

        // Create a payment for the refund handler to reference
        $payment = Payment::create([
            'user_id' => $maker->id,
            'gateway' => 'stripe',
            'gateway_reference' => 'pi_test_123',
            'amount' => 5000,
            'currency' => 'EGP',
            'status' => PaymentStatus::Succeeded,
        ]);

        $request = ApprovalRequest::create([
            'action' => 'payments.refund',
            'payload' => [
                'payment_id' => $payment->id,
                'amount' => 2000,
            ],
            'status' => ApprovalStatus::Pending,
            'requested_by' => $maker->id,
        ]);

        $service = app(ApprovalService::class);

        // Approve it the first time
        $approved = $service->approve($request, $approver);
        $this->assertEquals(ApprovalStatus::Executed, $approved->status);

        // Attempting to approve it again should throw a DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(__('governance.approval_already_decided'));

        $service->approve($approved, $approver);
    }

    public function test_approval_request_cannot_be_rejected_if_already_decided(): void
    {
        $maker = User::factory()->create();
        $approver = User::factory()->create();

        $request = ApprovalRequest::create([
            'action' => 'payments.refund',
            'payload' => [],
            'status' => ApprovalStatus::Rejected,
            'requested_by' => $maker->id,
            'decided_by' => $approver->id,
            'decided_at' => now(),
        ]);

        $service = app(ApprovalService::class);

        $this->expectException(DomainException::class);
        $service->approve($request, $approver);
    }

    public function test_non_admin_cannot_self_approve_request(): void
    {
        $user = User::factory()->create();

        $request = ApprovalRequest::create([
            'action' => 'payments.refund',
            'payload' => [],
            'status' => ApprovalStatus::Pending,
            'requested_by' => $user->id,
        ]);

        $service = app(ApprovalService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(__('governance.cannot_approve_own_request'));

        $service->approve($request, $user);
    }

    public function test_super_admin_can_self_approve_request(): void
    {
        Http::fake([
            'api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_test_123',
                'status' => 'succeeded',
            ]),
        ]);

        // Register the Spatie permission for admin.super
        Permission::findOrCreate('admin.super', 'web');

        $admin = User::factory()->create();
        $admin->givePermissionTo('admin.super');

        // Create a payment for the refund handler to reference
        $payment = Payment::create([
            'user_id' => $admin->id,
            'gateway' => 'stripe',
            'gateway_reference' => 'pi_test_123',
            'amount' => 5000,
            'currency' => 'EGP',
            'status' => PaymentStatus::Succeeded,
        ]);

        $request = ApprovalRequest::create([
            'action' => 'payments.refund',
            'payload' => [
                'payment_id' => $payment->id,
                'amount' => 2000,
            ],
            'status' => ApprovalStatus::Pending,
            'requested_by' => $admin->id,
        ]);

        $service = app(ApprovalService::class);

        $approved = $service->approve($request, $admin);

        $this->assertEquals(ApprovalStatus::Executed, $approved->status);
    }

    public function test_wallet_settle_hold_enforces_deterministic_lock_ordering(): void
    {
        $walletService = app(WalletService::class);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $walletA = $walletService->firstOrCreateFor($user1, 'EGP');
        $walletB = $walletService->firstOrCreateFor($user2, 'EGP');

        // Fund walletA and hold some money to settle
        $walletService->credit($walletA, Money::of(1000, 'EGP'));
        $walletService->hold($walletA, Money::of(500, 'EGP'));

        // Determine which wallet has the smaller UUID key
        $wallet1Id = (string) $walletA->getKey();
        $wallet2Id = (string) $walletB->getKey();
        $smallerUuid = strcmp($wallet1Id, $wallet2Id) < 0 ? $wallet1Id : $wallet2Id;
        $largerUuid = $smallerUuid === $wallet1Id ? $wallet2Id : $wallet1Id;

        // We will intercept DB query logging to assert that the SELECT query
        // for the smaller UUID is always executed before the larger UUID.
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'select') && str_contains($query->sql, 'wallets')) {
                $queries[] = $query;
            }
        });

        // Run settleHold (from A to B)
        $walletService->settleHold($walletA, $walletB, Money::of(500, 'EGP'));

        // Filter queries that target specific UUIDs
        $bindingsOrdered = [];
        foreach ($queries as $q) {
            foreach ($q->bindings as $binding) {
                if ($binding === $smallerUuid || $binding === $largerUuid) {
                    $bindingsOrdered[] = $binding;
                }
            }
        }

        // We expect the first bound parameter in the locked SELECT queries to be the smaller UUID
        $this->assertGreaterThanOrEqual(2, count($bindingsOrdered));
        $this->assertEquals($smallerUuid, $bindingsOrdered[0]);
        $this->assertEquals($largerUuid, $bindingsOrdered[1]);
    }
}
