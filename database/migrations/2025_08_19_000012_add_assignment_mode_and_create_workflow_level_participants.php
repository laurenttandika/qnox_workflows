<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workflow_levels', function (Blueprint $table) {
            $table->string('assignment_mode')->default('automatic')->after('sequence');
        });

        Schema::create('workflow_level_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_level_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('user');
            $table->string('participant_type');
            $table->unsignedBigInteger('participant_id');
            $table->index(
                ['participant_type', 'participant_id'],
                'workflow_level_participant_morph_index'
            );
            $table->string('role')->nullable();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_claim')->default(true);
            $table->boolean('can_act')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['workflow_level_id', 'participant_type', 'participant_id'],
                'workflow_level_participant_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_level_participants');

        Schema::table('workflow_levels', function (Blueprint $table) {
            $table->dropColumn('assignment_mode');
        });
    }
};
