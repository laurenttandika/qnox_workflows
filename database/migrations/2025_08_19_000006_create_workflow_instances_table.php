<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_id')->constrained();
            $t->morphs('subject');
            $t->nullableMorphs('initiator');
            $t->foreignId('current_level_id')->nullable()->constrained('workflow_levels');
            $t->string('status')->default('pending');
            $t->json('context')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('last_action_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
