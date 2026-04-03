<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_instance_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_instance_id')->constrained();
            $t->foreignId('workflow_level_id')->constrained('workflow_levels');
            $t->foreignId('parent_id')->nullable()->constrained('workflow_instance_levels');
            $t->nullableMorphs('assigned_to');
            $t->string('status')->default('pending');
            $t->string('action_key')->nullable();
            $t->text('comments')->nullable();
            $t->timestamp('entered_at')->nullable();
            $t->timestamp('exited_at')->nullable();
            $t->timestamp('receive_date')->nullable();
            $t->timestamp('forward_date')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instance_levels');
    }
};
