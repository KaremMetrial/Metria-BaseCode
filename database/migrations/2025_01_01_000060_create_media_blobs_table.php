<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_blobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('sha256', 64);
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('filename', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('virus_status', 20)->default('pending');
            $table->json('virus_scan_details')->nullable();
            
            // Enterprise cloud storage columns
            $table->string('storage_provider', 50)->nullable();
            $table->string('bucket', 100)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('etag', 100)->nullable();
            $table->string('storage_class', 50)->nullable();
            $table->string('encryption', 50)->nullable();
            $table->string('kms_key', 255)->nullable();
            $table->string('multipart_upload_id', 255)->nullable();

            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedBigInteger('access_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Constraints: Tenant-scoped deduplication
            $table->unique(['tenant_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_blobs');
    }
};
