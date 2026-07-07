<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Support\Money;
use App\Domain\Auth\Models\User;
use App\Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Providers\ExchangeRateProviderChain;
use App\Domain\Currency\Providers\MockExchangeRateProvider;
use App\Domain\Currency\Services\CurrencyConversionService;
use App\Domain\Currency\Services\ExchangeRateService;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrencyRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected ExchangeRateService $rateService;

    protected CurrencyConversionService $conversionService;

    protected ExchangeRateRepositoryInterface $repository;

    protected ExchangeRateProviderChain $providerChain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateService = $this->app->make(ExchangeRateService::class);
        $this->conversionService = $this->app->make(CurrencyConversionService::class);
        $this->repository = $this->app->make(ExchangeRateRepositoryInterface::class);
        $this->providerChain = $this->app->make(ExchangeRateProviderChain::class);

        // Seed default EGP currency
        Currency::create([
            'code' => 'EGP',
            'name' => ['en' => 'Egyptian Pound', 'ar' => 'جنيه مصري'],
            'symbol' => ['en' => 'EGP', 'ar' => 'ج.م'],
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * Test single default currency uniqueness.
     */
    public function test_default_currency_uniqueness(): void
    {
        // Add second currency with is_default = true
        $usd = Currency::create([
            'code' => 'USD',
            'name' => ['en' => 'US Dollar'],
            'symbol' => ['en' => '$'],
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => true,
        ]);

        // EGP is_default should now be false, USD is_default should be true
        $this->assertTrue(Currency::find('USD')->is_default);
        $this->assertFalse(Currency::find('EGP')->is_default);

        // Test database level constraint by directly inserting a default currency
        $this->expectException(QueryException::class);
        DB::table('currencies')->insert([
            'code' => 'EUR',
            'name' => '{"en":"Euro"}',
            'symbol' => '{"en":"€"}',
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * Test restriction on currency deletion.
     */
    public function test_currencies_cannot_be_deleted_once_referenced(): void
    {
        $usd = Currency::create([
            'code' => 'USD',
            'name' => ['en' => 'US Dollar'],
            'symbol' => ['en' => '$'],
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => false,
        ]);

        // Create a rate
        $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '48.25000000000000',
            'provider_name' => 'mock',
            'effective_at' => now(),
        ]);

        // Attempt deletion - should throw exception
        $this->expectException(DomainException::class);
        $usd->delete();
    }

    /**
     * Test non-overlapping validity windows.
     */
    public function test_non_overlapping_validity_windows(): void
    {
        Currency::create([
            'code' => 'USD',
            'name' => ['en' => 'US Dollar'],
            'symbol' => ['en' => '$'],
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => false,
        ]);

        $now = now();

        // 1. Store first rate (effective from T0 to far future)
        $rate1 = $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '45.00000000000000',
            'provider_name' => 'mock',
            'effective_at' => $now->copy()->subHours(10),
        ]);

        $this->assertEquals('2099-12-31 23:59:59', $rate1->fresh()->expires_at->toDateTimeString());

        // 2. Store second rate starting at T0 - 5 hours
        $rate2 = $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '48.00000000000000',
            'provider_name' => 'mock',
            'effective_at' => $now->copy()->subHours(5),
        ]);

        // First rate's expiration should be updated to match second rate's effective_at
        $this->assertEquals($rate2->effective_at->toDateTimeString(), $rate1->fresh()->expires_at->toDateTimeString());
        $this->assertEquals('2099-12-31 23:59:59', $rate2->fresh()->expires_at->toDateTimeString());

        // 3. Verify exactly one active rate at any hour
        $activeRateAtT9 = $this->rateService->getActiveRate('USD', $now->copy()->subHours(9));
        $this->assertEquals('45.00000000000000', $activeRateAtT9->rate_to_base);

        $activeRateAtT4 = $this->rateService->getActiveRate('USD', $now->copy()->subHours(4));
        $this->assertEquals('48.00000000000000', $activeRateAtT4->rate_to_base);
    }

    /**
     * Test stale rate policy.
     */
    public function test_stale_rate_policy(): void
    {
        Currency::create([
            'code' => 'USD',
            'name' => ['en' => 'US Dollar'],
            'symbol' => ['en' => '$'],
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => false,
        ]);

        $now = now();
        $rate = $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '48.00000000000000',
            'provider_name' => 'mock',
            'effective_at' => $now->copy()->subHours(30),
            'expires_at' => $now->copy()->subHours(26), // Expired 26 hours ago
        ]);

        // Attempting to retrieve now (26 hours after expiration) should throw stale exception
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Exchange rate for USD is stale');
        $this->rateService->getActiveRate('USD', $now);
    }

    /**
     * Test locked manual override precedence.
     */
    public function test_locked_manual_override_precedence(): void
    {
        Currency::create([
            'code' => 'USD',
            'name' => ['en' => 'US Dollar'],
            'symbol' => ['en' => '$'],
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => false,
        ]);

        $now = now();

        // Register manual rate with lock = true
        $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '50.00000000000000',
            'provider_name' => 'manual',
            'is_manual' => true,
            'is_locked' => true,
            'effective_at' => $now->copy()->subHours(2),
        ]);

        // Try to overwrite automatically (is_manual = false) - should be blocked
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot override rate for USD because a locked manual override covers this period');

        $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '48.00000000000000',
            'provider_name' => 'open_exchange_rates',
            'is_manual' => false,
            'is_locked' => false,
            'effective_at' => $now,
        ]);
    }

    /**
     * Test provider failover sequential logic.
     */
    public function test_provider_failover(): void
    {
        // Build mock drivers
        $primaryMock = new MockExchangeRateProvider;
        $primaryMock->setShouldFail(true); // Fails connection/timeout

        $failoverMock = new MockExchangeRateProvider;
        $failoverMock->setRate('USD', '49.00000000000000');

        $chain = new ExchangeRateProviderChain;
        $chain->registerProvider('open_exchange_rates', $primaryMock); // Primary
        $chain->registerProvider('ecb', $failoverMock); // Failover

        config(['currencies.providers.primary' => 'open_exchange_rates']);
        config(['currencies.providers.failovers' => ['ecb']]);

        // Fetching USD should failover to ecb (rate = 49)
        $rateData = $chain->fetchRate('USD');

        $this->assertEquals('49.00000000000000', $rateData['rate']);
        $this->assertEquals('mock', $rateData['provider_name']); // ecb uses mock driver
    }

    /**
     * Test Bankers Rounding (PHP_ROUND_HALF_EVEN) and Standard Rounding (PHP_ROUND_HALF_UP).
     */
    public function test_currency_conversion_and_rounding(): void
    {
        Currency::create([
            'code' => 'USD',
            'name' => ['en' => 'US Dollar'],
            'symbol' => ['en' => '$'],
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => false,
        ]);

        // Rate: 1 USD = 48.255 EGP
        $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '48.25500000000000',
            'provider_name' => 'mock',
            'effective_at' => now()->subHour(),
        ]);

        // Convert 10.00 USD to EGP
        // 10.00 * 48.255 = 482.55 EGP
        $usd = Money::of(1000, 'USD'); // 10.00 USD

        // Default Rounding is Banker's (PHP_ROUND_HALF_EVEN)
        $result = $this->conversionService->convert($usd, 'EGP');
        $this->assertEquals(48255, $result['money']->amount); // 482.55 EGP -> 48255 minor units

        // Flush cache to ensure clean state
        Cache::flush();

        // Test Banker's Rounding behavior: round half even.
        // 0.025 EGP should round to 0.02 (even)
        $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '1.02500000000000',
            'provider_name' => 'mock',
            'effective_at' => now()->subSeconds(20),
        ]);

        $oneDollar = Money::of(100, 'USD'); // 1.00 USD

        // 1.00 * 1.025 = 1.025 => Banker's rounds to nearest even => 1.02 (102 minor)
        $resEven = $this->conversionService->convert($oneDollar, 'EGP', PHP_ROUND_HALF_EVEN);
        $this->assertEquals(102, $resEven['money']->amount);

        // Flush cache to ensure clean state
        Cache::flush();

        // 1.00 * 1.035 = 1.035 => Banker's rounds to nearest even => 1.04 (104 minor)
        $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '1.03500000000000',
            'provider_name' => 'mock',
            'effective_at' => now()->subSeconds(10),
        ]);
        $resOdd = $this->conversionService->convert($oneDollar, 'EGP', PHP_ROUND_HALF_EVEN);
        $this->assertEquals(104, $resOdd['money']->amount);

        // Flush cache to ensure clean state
        Cache::flush();

        // Standard Math (PHP_ROUND_HALF_UP) rounds 1.025 to 1.03 (103 minor)
        $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '1.02500000000000',
            'provider_name' => 'mock',
            'effective_at' => now()->subSeconds(5),
        ]);
        $resHalfUp = $this->conversionService->convert($oneDollar, 'EGP', PHP_ROUND_HALF_UP);
        $this->assertEquals(103, $resHalfUp['money']->amount);
    }

    /**
     * Test JPY (0 minor units) and BHD (3 minor units) conversion.
     */
    public function test_currencies_with_zero_and_three_minor_units(): void
    {
        Currency::create([
            'code' => 'BHD',
            'name' => ['en' => 'Bahraini Dinar'],
            'symbol' => ['en' => 'BD'],
            'minor_units' => 3,
            'is_active' => true,
            'is_default' => false,
        ]);

        Currency::create([
            'code' => 'JPY',
            'name' => ['en' => 'Japanese Yen'],
            'symbol' => ['en' => '¥'],
            'minor_units' => 0,
            'is_active' => true,
            'is_default' => false,
        ]);

        // Rates to base (EGP):
        // 1 BHD = 128.525 EGP
        $this->rateService->registerRate([
            'currency_code' => 'BHD',
            'rate_to_base' => '128.52500000000000',
            'provider_name' => 'mock',
            'effective_at' => now()->subHour(),
        ]);

        // 1 JPY = 0.315 EGP
        $this->rateService->registerRate([
            'currency_code' => 'JPY',
            'rate_to_base' => '0.31500000000000',
            'provider_name' => 'mock',
            'effective_at' => now()->subHour(),
        ]);

        // Convert 1000 EGP to BHD (base to foreign = division)
        // 1000 EGP / 128.525 = 7.780587... BHD
        // 3 minor units. Banker's rounds 7.78058... to 7.781 BHD (7781 minor units)
        $egp = Money::of(100000, 'EGP'); // 1000.00 EGP
        $bhdRes = $this->conversionService->convert($egp, 'BHD');
        $this->assertEquals(7781, $bhdRes['money']->amount); // 7.781 BHD
        $this->assertEquals(3, $bhdRes['money']::minorUnitsFor('BHD'));

        // Convert 100 EGP to JPY (base to foreign = division)
        // 100 EGP / 0.315 = 317.46... JPY
        // 0 minor units. Rounds to 317 JPY
        $egp2 = Money::of(10000, 'EGP'); // 100.00 EGP
        $jpyRes = $this->conversionService->convert($egp2, 'JPY');
        $this->assertEquals(317, $jpyRes['money']->amount); // 317 JPY
    }

    /**
     * Test Payment Snapshot capturing rate details.
     */
    public function test_payment_snapshot_integrity(): void
    {
        Currency::create([
            'code' => 'USD',
            'name' => ['en' => 'US Dollar'],
            'symbol' => ['en' => '$'],
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '48.25300000000000',
            'provider_name' => 'mock_provider',
            'effective_at' => now()->subHour(),
        ]);

        $user = User::factory()->create();

        // Perform conversion
        $usdMoney = Money::of(10000, 'USD'); // 100.00 USD
        $conversion = $this->conversionService->convert($usdMoney, 'EGP');

        $this->assertEquals(482530, $conversion['snapshot']['converted_amount']); // 4825.30 EGP

        // Create Payment capturing conversion snapshot details
        $payment = Payment::create(array_merge([
            'user_id' => $user->id,
            'gateway' => 'stripe',
            'gateway_reference' => 'ch_test_123',
            'amount' => 10000, // 100.00 USD
            'currency' => 'USD',
            'status' => PaymentStatus::Succeeded,
        ], $conversion['snapshot']));

        $payment = $payment->fresh();

        $this->assertEquals('USD', $payment->source_currency);
        $this->assertEquals('EGP', $payment->target_currency);
        $this->assertEquals(482530, $payment->converted_amount);
        $this->assertEquals('4825.3000', $payment->converted_amount_decimal);
        $this->assertEquals('48.25300000000000', $payment->exchange_rate);
        $this->assertEquals('mock_provider', $payment->rate_provider);
        $this->assertEquals('v1', $payment->conversion_algorithm_version);
    }

    /**
     * Property-based conversion test (fuzzing/round-trip bounds checking).
     */
    public function test_property_based_conversion(): void
    {
        Currency::create([
            'code' => 'USD',
            'name' => ['en' => 'US Dollar'],
            'symbol' => ['en' => '$'],
            'minor_units' => 2,
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->rateService->registerRate([
            'currency_code' => 'USD',
            'rate_to_base' => '48.25300000000000',
            'provider_name' => 'mock',
            'effective_at' => now()->subHour(),
        ]);

        // Generate 100 random amounts, convert round-trip, and verify mathematical bounds
        for ($i = 0; $i < 100; $i++) {
            $randomMinorAmount = rand(100, 1000000); // 1.00 USD to 10,000.00 USD
            $usd = Money::of($randomMinorAmount, 'USD');

            // USD -> EGP
            $egpRes = $this->conversionService->convert($usd, 'EGP');
            $egpMoney = $egpRes['money'];

            // EGP -> USD
            $usdRes = $this->conversionService->convert($egpMoney, 'USD');
            $usdReturned = $usdRes['money'];

            // Round-trip value should be extremely close to original (within 1 cent/piaster difference due to rounding)
            $difference = abs($usd->amount - $usdReturned->amount);
            $this->assertLessThanOrEqual(1, $difference, "Fuzz round-trip failed for amount: {$randomMinorAmount}");
        }
    }
}
