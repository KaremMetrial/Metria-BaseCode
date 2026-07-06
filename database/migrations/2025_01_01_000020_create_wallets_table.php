<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0); // minor units
            $table->unsignedBigInteger('held')->default(0);    // escrowed portion of balance
            $table->char('currency', 3);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('type', 20)->index();               // credit/debit/hold/release/capture
            $table->unsignedBigInteger('amount');              // minor units, direction from type
            $table->unsignedBigInteger('balance_after');
            $table->unsignedBigInteger('held_after');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->index(); // append-only ledger

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
