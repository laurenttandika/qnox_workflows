<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_transitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_id')->constrained();
            $t->foreignId('from_level_id')->constrained('workflow_levels');
            $t->foreignId('to_level_id')->nullable()->constrained('workflow_levels');
            $t->string('action_key');
            $t->string('direction')->default('forward');
            $t->json('guard')->nullable();
            $t->string('label')->nullable();
            $t->string('status')->nullable();
            $t->json('meta')->nullable();
            $t->json('form_schema')->nullable();
            $t->timestamps();

            $t->unique(['workflow_id', 'from_level_id', 'action_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};
