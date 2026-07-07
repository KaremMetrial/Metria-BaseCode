<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('gateway', 40)->index();
            $table->string('gateway_reference')->nullable();
            $table->unsignedBigInteger('amount');              // minor units
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->char('currency', 3);

            // Captured Rate Snapshot & Converted Amounts
            $table->char('source_currency', 3)->nullable();
            $table->char('target_currency', 3)->nullable();
            $table->unsignedBigInteger('converted_amount')->nullable(); // target currency minor units
            $table->decimal('converted_amount_decimal', 24, 4)->nullable();
            $table->decimal('exchange_rate', 24, 14)->nullable();
            $table->string('rate_provider', 50)->nullable();
            $table->string('rate_provider_version', 20)->nullable();
            $table->string('conversion_direction', 10)->nullable(); // 'multiply' or 'divide'
            $table->string('rounding_mode_used', 20)->nullable();
            $table->string('conversion_algorithm_version', 10)->nullable(); // 'v1', 'v2'
            $table->timestamp('rate_captured_at')->nullable();

            $table->string('status', 30)->index();
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Webhooks correlate by (gateway, gateway_reference).
            $table->unique(['gateway', 'gateway_reference']);

            // Audit Safety Foreign Keys
            $table->foreign('source_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('target_currency')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
