<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('project_updates')) return;

        Schema::create('project_updates', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // What changed
            $t->string('old_status')->nullable();
            $t->string('new_status')->nullable();
            $t->unsignedTinyInteger('progress_before')->nullable();
            $t->unsignedTinyInteger('progress_after')->nullable();

            // Snapshots of checklist/comments (keep full history)
            $t->json('checklist_before')->nullable();
            $t->json('checklist_after')->nullable();
            $t->text('comment')->nullable();

            // Who did it & where it originated
            $t->foreignId('updated_by')->nullable()->index(); // users.id
            $t->string('source')->nullable(); // 'web', 'import', 'api'

            $t->timestamps();

            $t->index(['new_status','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
    }
};
