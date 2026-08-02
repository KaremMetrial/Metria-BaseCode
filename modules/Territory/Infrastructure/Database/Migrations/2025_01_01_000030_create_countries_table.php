<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->json('name'); // {"en": "Egypt", "ar": "مصر"}
            $table->char('iso_code_2', 2)->unique(); // EG, SA, AE
            $table->char('iso_code_3', 3)->unique(); // EGY, SAU, ARE
            $table->string('phone_code', 10);        // +20, +966
            $table->char('currency', 3);             // EGP, SAR
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
