<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('variant', 50); // e.g. thumbnail, medium, webp, avif
            $table->string('path', 500);
            $table->string('mime_type', 100);
            $table->string('checksum', 64)->nullable();
            $table->string('hash_algorithm', 20)->default('sha256');
            $table->string('disk', 50);
            $table->string('storage_provider', 50)->nullable();
            $table->boolean('is_generated')->default(true);
            $table->integer('processing_time_ms')->nullable();
            
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->unsignedBigInteger('size');

            $table->timestamps();

            $table->unique(['media_id', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};
