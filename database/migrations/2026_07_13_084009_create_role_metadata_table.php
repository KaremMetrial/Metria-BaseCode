<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_metadata', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            
            // Enterprise controls
            $table->boolean('is_system')->default(false)->comment('If true, role cannot be deleted');
            $table->boolean('is_editable')->default(true)->comment('If false, permissions cannot be changed');
            $table->boolean('is_assignable')->default(true)->comment('If false, cannot be assigned to new users');
            
            // Audit
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            $table->unique('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_metadata');
    }
};
