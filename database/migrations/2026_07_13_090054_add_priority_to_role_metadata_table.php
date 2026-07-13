<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_metadata', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')->default(100)->after('description')->comment('Lower number = higher power (1 = Super Admin)');
        });
    }

    public function down(): void
    {
        Schema::table('role_metadata', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
