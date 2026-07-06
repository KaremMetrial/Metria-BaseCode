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
            $table->string('status', 30)->index();
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Webhooks correlate by (gateway, gateway_reference).
            $table->unique(['gateway', 'gateway_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
