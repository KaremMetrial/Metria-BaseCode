<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('type', 48);
            $table->string('state', 32)->default('active');
            $table->string('title', 255)->nullable();
            $table->uuid('created_by');
            $table->string('direct_key', 64)->nullable();
            $table->unsignedBigInteger('next_sequence')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'state', 'updated_at'], 'comm_conv_tenant_state_updated_idx');
            $table->unique(['tenant_id', 'type', 'direct_key'], 'communication_direct_conversation_unique');
        });

        Schema::create('communication_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('conversation_id')->index();
            $table->uuid('actor_id')->index();
            $table->string('role', 32);
            $table->string('state', 32)->default('active');
            $table->unsignedBigInteger('last_read_sequence')->default(0);
            $table->unsignedBigInteger('last_delivered_sequence')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['conversation_id', 'actor_id'], 'comm_member_conv_actor_unique');
            $table->index(['tenant_id', 'actor_id', 'state', 'updated_at'], 'comm_member_tenant_actor_state_updated_idx');
        });

        Schema::create('communication_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('conversation_id')->index();
            $table->uuid('author_actor_id')->index();
            $table->unsignedBigInteger('sequence');
            $table->string('kind', 48);
            $table->json('content');
            $table->string('state', 32)->default('active');
            $table->unsignedInteger('revision')->default(1);
            $table->uuid('client_message_id')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'sequence'], 'comm_message_conv_sequence_unique');
            $table->unique(['tenant_id', 'author_actor_id', 'client_message_id'], 'communication_message_actor_client_unique');
            $table->index(['tenant_id', 'conversation_id', 'sequence'], 'comm_msg_tenant_conv_sequence_idx');
        });

        Schema::create('communication_sync_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('conversation_id')->index();
            $table->unsignedInteger('change_version');
            $table->string('change_type', 64);
            $table->uuid('message_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'change_version'], 'comm_sync_change_conv_version_unique');
            $table->index(['tenant_id', 'conversation_id', 'change_version'], 'comm_sync_tenant_conv_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_sync_changes');
        Schema::dropIfExists('communication_messages');
        Schema::dropIfExists('communication_memberships');
        Schema::dropIfExists('communication_conversations');
    }
};
