<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('name');
            $table->string('format')->default('{prefix}/{year}/{number}');
            $table->string('prefix')->nullable();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->unsignedSmallInteger('padding')->default(6);
            $table->string('reset_period')->default('never');
            $table->string('reset_marker')->nullable();
            $table->nullableMorphs('scope');
            $table->timestamp('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['key', 'scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_number_sequences');
    }
};
