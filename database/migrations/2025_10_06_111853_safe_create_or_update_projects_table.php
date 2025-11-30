<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Create if missing
        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name')->nullable();               // Project
                $t->string('client')->nullable();
                $t->string('location')->nullable();
                $t->string('area')->nullable();               // Eastern / Central / Western
                $t->string('quotation_no')->nullable();
                $t->string('atai_products')->nullable();
                $t->decimal('quotation_value', 14, 2)->default(0);
                $t->string('status')->nullable();             // bidding | in-hand | lost (or your exact labels)
                // New fields used by the UI
                $t->unsignedTinyInteger('progress_pct')->default(0);
                $t->json('checklist_json')->nullable();       // compact & extensible
                $t->text('last_comment')->nullable();
                $t->foreignId('updated_by')->nullable()->index(); // users.id
                $t->timestamps();
                $t->softDeletes();

                $t->index(['area', 'status']);
                $t->index('quotation_no');
            });
            return;
        }

        // Table exists → only add missing columns (safe for prod)
        Schema::table('projects', function (Blueprint $t) {
            if (!Schema::hasColumn('projects','status'))        $t->string('status')->nullable()->after('quotation_value')->index();
            if (!Schema::hasColumn('projects','progress_pct'))  $t->unsignedTinyInteger('progress_pct')->default(0)->after('status');
            if (!Schema::hasColumn('projects','checklist_json'))$t->json('checklist_json')->nullable()->after('progress_pct');
            if (!Schema::hasColumn('projects','last_comment'))  $t->text('last_comment')->nullable()->after('checklist_json');
            if (!Schema::hasColumn('projects','updated_by'))    $t->foreignId('updated_by')->nullable()->index()->after('last_comment');
            if (!Schema::hasColumn('projects','deleted_at'))    $t->softDeletes();
            if (!Schema::hasColumn('projects','created_at'))    $t->timestamps(); // adds both created_at & updated_at
        });
    }

    public function down(): void
    {
        // We do not drop the main business table; only remove added columns if you must.
        Schema::table('projects', function (Blueprint $t) {
            if (Schema::hasColumn('projects','progress_pct'))   $t->dropColumn('progress_pct');
            if (Schema::hasColumn('projects','checklist_json')) $t->dropColumn('checklist_json');
            if (Schema::hasColumn('projects','last_comment'))   $t->dropColumn('last_comment');
            if (Schema::hasColumn('projects','updated_by'))     $t->dropColumn('updated_by');
            if (Schema::hasColumn('projects','deleted_at'))     $t->dropSoftDeletes();
        });
    }
};
