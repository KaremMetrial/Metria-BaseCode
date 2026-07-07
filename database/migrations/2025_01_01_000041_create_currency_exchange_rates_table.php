<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $precision = config('currencies.precision', 24);
        $scale = config('currencies.scale', 14);

        Schema::create('currency_exchange_rates', function (Blueprint $table) use ($precision, $scale) {
            $table->uuid('id')->primary();
            $table->char('currency_code', 3)->index();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->decimal('rate_to_base', $precision, $scale);

            // Metadata
            $table->string('provider_name', 50);
            $table->string('provider_version', 20)->nullable();
            $table->string('api_schema_version', 20)->nullable();
            $table->string('request_id', 50)->nullable();
            $table->string('provider_response_hash', 64)->nullable();
            $table->uuid('sync_batch_id')->nullable();

            // Manual overrides
            $table->boolean('is_manual')->default(false);
            $table->boolean('is_locked')->default(false);

            $table->timestamp('effective_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            // Composite indexes for fast timeline and active rate lookups
            $table->index(['currency_code', 'effective_at', 'expires_at'], 'idx_rates_window');
            $table->index(['currency_code', 'effective_at'], 'idx_rates_effective');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rates');
    }
};
