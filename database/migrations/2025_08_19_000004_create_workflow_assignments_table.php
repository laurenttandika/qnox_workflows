<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_level_id')->constrained();
            $t->nullableMorphs('assignable'); // User/Department/Role/etc
            $t->string('type')->nullable();
            $t->json('criteria')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_assignments');
    }
};
