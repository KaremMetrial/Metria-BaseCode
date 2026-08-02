<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governorates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained('countries')->cascadeOnDelete();
            $table->json('name'); // {"en": "Cairo", "ar": "القاهرة"}
            $table->string('code', 50)->nullable()->index(); // e.g. CAI, GIZ, RY
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['country_id', 'is_active']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governorates');
    }
};
