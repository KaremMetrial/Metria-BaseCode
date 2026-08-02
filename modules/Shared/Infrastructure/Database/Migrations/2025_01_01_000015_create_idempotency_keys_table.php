<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');                    // raw Idempotency-Key header
            $table->string('scope_hash', 64)->unique(); // sha256(key|method|path|user)
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamps();

            $table->index('created_at'); // pruned by governance:prune
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
