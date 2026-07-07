<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('media_blob_id')->nullable()->constrained('media_blobs')->nullOnDelete();
            
            // Polymorphic relations
            $table->string('mediable_type')->nullable();
            $table->string('mediable_id')->nullable();
            
            $table->string('media_type', 30); // image, video, audio, document, archive
            $table->string('purpose', 50)->nullable();    // avatar, logo, attachment, thumbnail, preview
            $table->boolean('is_public')->default(false);
            $table->string('status', 30)->index();          // pending, uploading, uploaded, verifying, processing, active, quarantined, failed, deleted
            
            $table->string('checksum', 64)->nullable();
            $table->string('hash_algorithm', 20)->default('sha256');
            $table->json('custom_properties')->nullable();
            
            $table->string('moderation_status', 30)->default('pending'); // pending, approved, flagged
            $table->json('moderation_details')->nullable();
            
            $table->string('processing_error', 500)->nullable();
            $table->integer('retry_count')->default(0);
            
            // Lifecycle timestamps
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_finished_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('quarantined_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mediable_type', 'mediable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
