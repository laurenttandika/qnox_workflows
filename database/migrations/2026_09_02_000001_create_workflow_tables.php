<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('module_key')->index();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['module_key', 'slug']);
        });

        Schema::create('workflow_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sequence');
            $table->string('approver_type', 20);
            $table->string('approver_role')->nullable();
            $table->boolean('rejection_comment_required')->default(true);
            $table->timestamps();
            $table->unique(['workflow_id', 'sequence']);
        });

        Schema::create('workflow_level_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_level_id')->constrained('workflow_levels')->cascadeOnDelete();
            $table->string('user_id');
            $table->timestamps();
            $table->unique(['workflow_level_id', 'user_id']);
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->restrictOnDelete();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->index(['subject_type', 'subject_id']);
            $table->string('initiator_type');
            $table->string('initiator_id');
            $table->index(['initiator_type', 'initiator_id']);
            $table->foreignId('current_level_id')->nullable()->constrained('workflow_levels')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->json('context')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_action_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'current_level_id']);
        });

        Schema::create('workflow_instance_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_level_id')->nullable()->constrained('workflow_levels')->nullOnDelete();
            $table->string('level_name');
            $table->unsignedInteger('level_sequence');
            $table->string('approver_type', 20);
            $table->boolean('rejection_comment_required')->default(true);
            $table->string('status', 20)->default('pending');
            $table->timestamp('entered_at');
            $table->timestamp('actioned_at')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();
            $table->index(['workflow_instance_id', 'status']);
        });

        Schema::create('workflow_instance_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_level_id')->constrained()->cascadeOnDelete();
            $table->string('approver_type');
            $table->string('approver_id');
            $table->string('status', 20)->default('pending');
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_instance_level_id', 'approver_type', 'approver_id'], 'workflow_instance_approver_unique');
            $table->index(['approver_type', 'approver_id', 'status'], 'workflow_instance_approver_lookup');
        });

        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_instance_level_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type');
            $table->string('actor_id');
            $table->index(['actor_type', 'actor_id']);
            $table->string('action', 20);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_inbox_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_instance_level_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_type');
            $table->string('recipient_id');
            $table->string('status', 20)->default('pending');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('workflow_action_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['workflow_instance_level_id', 'recipient_type', 'recipient_id'], 'workflow_inbox_level_recipient_unique');
            $table->index(['recipient_type', 'recipient_id', 'status'], 'workflow_inbox_recipient_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_inbox_items');
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflow_instance_approvers');
        Schema::dropIfExists('workflow_instance_levels');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_level_users');
        Schema::dropIfExists('workflow_levels');
        Schema::dropIfExists('workflows');
    }
};
