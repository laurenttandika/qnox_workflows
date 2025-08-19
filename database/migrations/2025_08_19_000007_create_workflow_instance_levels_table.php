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
            $t->timestamp('entered_at')->nullable();
            $t->timestamp('exited_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instance_levels');
    }
};