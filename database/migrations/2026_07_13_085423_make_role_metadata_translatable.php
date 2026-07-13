<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_metadata', function (Blueprint $table) {
            $table->json('display_name')->nullable()->change();
            $table->json('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('role_metadata', function (Blueprint $table) {
            $table->string('display_name')->nullable()->change();
            $table->text('description')->nullable()->change();
        });
    }
};
