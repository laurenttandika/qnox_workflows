<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_instance_id')->constrained();
            $t->foreignId('from_level_id')->nullable()->constrained('workflow_levels');
            $t->foreignId('to_level_id')->nullable()->constrained('workflow_levels');
            $t->morphs('actor');
            $t->string('action_key');
            $t->text('comment')->nullable();
            $t->json('payload')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_actions');
    }
};