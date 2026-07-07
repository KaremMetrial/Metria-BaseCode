<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnumApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_all_registered_system_enums(): void
    {
        $response = $this->getJson('/api/v1/enums');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payment_status',
                    'approval_status',
                    'wallet_transaction_type',
                ],
            ])
            ->assertJsonFragment([
                'name' => 'Pending',
                'value' => 'pending',
                'label' => 'Pending',
            ])
            ->assertJsonFragment([
                'name' => 'PartiallyRefunded',
                'value' => 'partially_refunded',
                'label' => 'Partially Refunded',
            ]);
    }

    public function test_it_returns_a_specific_enum_by_key(): void
    {
        $response = $this->getJson('/api/v1/enums/payment_status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'key' => 'payment_status',
                ],
            ])
            ->assertJsonFragment([
                'name' => 'Succeeded',
                'value' => 'succeeded',
                'label' => 'Succeeded',
            ]);
    }

    public function test_it_returns_404_for_unknown_enum_key(): void
    {
        $response = $this->getJson('/api/v1/enums/non_existent_enum');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'enum_not_found',
                ],
            ]);
    }
}
