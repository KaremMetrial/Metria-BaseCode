<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->json('name'); // {"en": "New Cairo Logistics Zone", "ar": "نطاق توصيل القاهرة الجديدة"}
            $table->string('code', 100)->index(); // unique per tenant handled in app logic

            // Flexible geospatial storage (JSON array of [lat, lng] polygon points)
            $table->json('polygon_coordinates')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['city_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
