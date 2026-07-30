<?php

declare(strict_types=1);

namespace App\Domain\Currency\Console\Commands;

use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Providers\ExchangeRateProviderChain;
use App\Domain\Currency\Services\ExchangeRateService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncExchangeRates extends Command
{
    protected $signature = 'currencies:sync';

    protected $description = 'Sync active exchange rates from providers and log payloads';

    public function handle(
        ExchangeRateProviderChain $providerChain,
        ExchangeRateService $rateService
    ): int {
        $this->info('Starting exchange rate synchronization...');

        $baseCurrency = config('currencies.base_currency', config('payments.currency', 'EGP'));
        $activeCurrencies = Currency::where('is_active', true)
            ->where('code', '!=', $baseCurrency)
            ->get();

        if ($activeCurrencies->isEmpty()) {
            $this->warn('No active foreign currencies found to sync.');

            return 0;
        }

        $syncBatchId = Str::uuid()->toString();

        foreach ($activeCurrencies as $currency) {
            $this->info("Syncing currency: {$currency->code}");

            try {
                // Fetch rate through provider chain
                $rateData = $providerChain->fetchRate($currency->code);

                DB::transaction(function () use ($rateService, $currency, $rateData, $syncBatchId) {
                    // Register the rate (handles overlapping windows)
                    $rateService->registerRate([
                        'currency_code' => $currency->code,
                        'rate_to_base' => $rateData['rate'],
                        'provider_name' => $rateData['provider_name'] ?? 'mock',
                        'provider_version' => $rateData['provider_version'] ?? '1.0',
                        'api_schema_version' => 'v1',
                        'request_id' => $rateData['request_id'] ?? null,
                        'provider_response_hash' => $rateData['response_hash'] ?? null,
                        'sync_batch_id' => $syncBatchId,
                        'is_manual' => false,
                        'is_locked' => false,
                        'effective_at' => now(),
                    ]);

                    // Write to isolated sync log table
                    DB::table('currency_exchange_rate_sync_logs')->insert([
                        'id' => Str::uuid()->toString(),
                        'sync_batch_id' => $syncBatchId,
                        'provider_name' => $rateData['provider_name'] ?? 'mock',
                        'status' => 'success',
                        'request_id' => $rateData['request_id'] ?? null,
                        'original_payload' => $rateData['original_payload'] ?? null,
                        'error_message' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

                $this->info("Successfully synced currency {$currency->code}.");

            } catch (Exception $e) {
                $this->error("Failed syncing currency {$currency->code}: ".$e->getMessage());

                // Log failure to sync log table
                DB::table('currency_exchange_rate_sync_logs')->insert([
                    'id' => Str::uuid()->toString(),
                    'sync_batch_id' => $syncBatchId,
                    'provider_name' => 'chain',
                    'status' => 'failed',
                    'request_id' => null,
                    'original_payload' => null,
                    'error_message' => $e->getMessage(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->info('Exchange rate synchronization completed.');

        return 0;
    }
}
