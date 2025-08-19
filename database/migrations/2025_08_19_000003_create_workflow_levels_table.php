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
            $t->json('rules')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_levels');
    }
};
