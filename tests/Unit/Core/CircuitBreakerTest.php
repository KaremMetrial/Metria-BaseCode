<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Exceptions\IntegrationException;
use App\Domain\Integration\Support\CircuitBreaker;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    public function test_circuit_breaker_opens_after_configured_threshold(): void
    {
        $breaker = new CircuitBreaker(threshold: 3, cooldownSeconds: 10);
        $service = 'sms_gateway';

        $this->assertFalse($breaker->isOpen($service));

        // First failure
        try {
            $breaker->call($service, function () {
                throw new \Exception('Failed connection');
            });
        } catch (\Throwable $e) {
            $this->assertSame('Failed connection', $e->getMessage());
        }
        $this->assertFalse($breaker->isOpen($service));

        // Second failure
        try {
            $breaker->call($service, function () {
                throw new \Exception('Failed connection 2');
            });
        } catch (\Throwable $e) {
            $this->assertSame('Failed connection 2', $e->getMessage());
        }
        $this->assertFalse($breaker->isOpen($service));

        // Third failure - should trip the circuit open
        try {
            $breaker->call($service, function () {
                throw new \Exception('Failed connection 3');
            });
        } catch (\Throwable $e) {
            $this->assertSame('Failed connection 3', $e->getMessage());
        }
        $this->assertTrue($breaker->isOpen($service));

        // Calling when open should fail-fast with IntegrationException
        $this->expectException(IntegrationException::class);
        $breaker->call($service, function () {
            return 'should not run';
        });
    }

    public function test_circuit_breaker_success_resets_failures(): void
    {
        $breaker = new CircuitBreaker(threshold: 2, cooldownSeconds: 10);
        $service = 'email_gateway';

        // 1 failure
        try {
            $breaker->call($service, function () {
                throw new \Exception('Failed');
            });
        } catch (\Throwable $e) {
        }

        // Success should reset failures
        $result = $breaker->call($service, function () {
            return 'success';
        });
        $this->assertSame('success', $result);

        // Another failure (total would be 2, but reset occurred, so it's 1 again)
        try {
            $breaker->call($service, function () {
                throw new \Exception('Failed again');
            });
        } catch (\Throwable $e) {
        }

        $this->assertFalse($breaker->isOpen($service));
    }
}
