<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_group_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('moduleable');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('handler')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreignId('workflow_module_id')
                ->nullable()
                ->after('workflow_group_id')
                ->constrained('workflow_modules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_module_id');
        });

        Schema::dropIfExists('workflow_modules');
    }
};
