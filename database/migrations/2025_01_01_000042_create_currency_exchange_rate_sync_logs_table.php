<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_exchange_rate_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sync_batch_id')->nullable()->index();
            $table->string('provider_name', 50);
            $table->string('status', 20); // 'success', 'failed'
            $table->string('request_id', 50)->nullable();
            $table->longText('original_payload')->nullable(); // Large JSON payload separated from primary rates
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rate_sync_logs');
    }
};
