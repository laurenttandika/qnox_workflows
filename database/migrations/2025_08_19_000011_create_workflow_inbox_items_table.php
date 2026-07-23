<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_inbox_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_instance_level_id')
                ->constrained('workflow_instance_levels')
                ->cascadeOnDelete();
            $table->morphs('recipient');
            $table->string('status')->default('new');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('workflow_action_id')
                ->nullable()
                ->constrained('workflow_actions')
                ->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['workflow_instance_level_id', 'recipient_type', 'recipient_id'],
                'workflow_inbox_level_recipient_unique'
            );
            $table->index(
                ['recipient_type', 'recipient_id', 'status'],
                'workflow_inbox_recipient_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_inbox_items');
    }
};
