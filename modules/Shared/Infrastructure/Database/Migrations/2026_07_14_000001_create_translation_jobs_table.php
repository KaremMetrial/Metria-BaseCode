<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_type', 'model_id']);
            $table->string('locale', 10)->index();
            $table->string('status', 30)->default('pending')->index(); // pending, processing, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_jobs');
    }
};
