<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_id')->constrained();
            $t->string('name');
            $t->unsignedInteger('sequence')->index();
            $t->boolean('is_start')->default(false);
            $t->boolean('is_terminal')->default(false);
            $t->text('description')->nullable();
            $t->text('msg_next')->nullable();
            $t->boolean('allow_rejection')->default(true);
            $t->boolean('allow_repeat_participate')->default(true);
            $t->boolean('allow_round_robin')->default(false);
            $t->boolean('has_next_start_optional')->default(true);
            $t->boolean('is_optional')->default(false);
            $t->boolean('is_approval')->default(true);
            $t->boolean('can_close')->default(true);
            $t->string('action_description', 120)->nullable();
            $t->string('status_description', 120)->nullable();
            $t->json('rules')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_levels');
    }
};
