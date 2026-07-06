<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createPaymentForUser(User $user): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'gateway' => 'stripe',
            'gateway_reference' => 'pi_test_' . uniqid(),
            'amount' => 5000,
            'currency' => 'EGP',
            'status' => PaymentStatus::Succeeded,
        ]);
    }

    public function test_user_can_view_own_payment_and_wallet(): void
    {
        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id], ['currency' => 'EGP']);

        Sanctum::actingAs($user);

        $paymentResponse = $this->getJson("/api/v1/payments/{$payment->id}");
        $paymentResponse->assertStatus(200);
        $paymentResponse->assertJsonPath('data.id', $payment->id);

        $walletResponse = $this->getJson('/api/v1/wallet');
        $walletResponse->assertStatus(200);
    }

    public function test_user_cannot_view_another_users_payment(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $paymentOfB = $this->createPaymentForUser($userB);

        Sanctum::actingAs($userA);

        $response = $this->getJson("/api/v1/payments/{$paymentOfB->id}");
        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_any_payment_via_policy_override(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'admin.super', 'guard_name' => 'web']);
        $admin->givePermissionTo($permission);

        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/payments/{$payment->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $payment->id);
    }
}
