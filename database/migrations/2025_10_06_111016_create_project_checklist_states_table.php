<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('project_checklist_states')) return;

        Schema::create('project_checklist_states', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('project_id');
            $table->string('item_key', 64);     // e.g. 'mep_contractor_appointed'
            $table->boolean('checked')->default(false);
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'item_key'], 'uq_project_item');
            $table->index(['item_key', 'checked']);

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            if (!Schema::hasColumn('project_checklist_states', 'phase')) {
                $t->string('phase', 32)->default('BIDDING')->after('project_id');
            }
            if (!Schema::hasColumn('project_checklist_states', 'mep_contractor_appointed')) {
                $t->boolean('mep_contractor_appointed')->default(false);
            }
            if (!Schema::hasColumn('project_checklist_states', 'boq_quoted')) {
                $t->boolean('boq_quoted')->default(false);
            }
            if (!Schema::hasColumn('project_checklist_states', 'boq_submitted')) {
                $t->boolean('boq_submitted')->default(false);
            }
            if (!Schema::hasColumn('project_checklist_states', 'priced_at_discount')) {
                $t->boolean('priced_at_discount')->default(false);
            }
            if (!Schema::hasColumn('project_checklist_states', 'progress')) {
                $t->unsignedTinyInteger('progress')->default(0);
            }
        });

        Schema::table('project_checklist_states', function (Blueprint $t) {
            $t->unique(['project_id', 'phase'], 'uniq_project_phase');
        });
    }

    public function down(): void {
        Schema::table('project_checklist_states', function (Blueprint $t) {
            if (Schema::hasColumn('project_checklist_states', 'phase')) {
                $t->dropUnique('uniq_project_phase');
                $t->dropColumn('phase');
            }
            // (keep the other columns if you like; remove as needed)
        });
    }
};
