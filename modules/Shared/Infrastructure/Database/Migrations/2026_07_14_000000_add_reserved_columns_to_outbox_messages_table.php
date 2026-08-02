<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->timestamp('reserved_at')->nullable()->index();
            $table->uuid('reserved_by')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropColumn(['reserved_at', 'reserved_by']);
        });
    }
};
