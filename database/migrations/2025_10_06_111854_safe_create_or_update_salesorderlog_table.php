<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('salesorderlog')) {
            Schema::create('salesorderlog', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('so_no')->nullable();
                $t->date('date_rec')->nullable();
                $t->string('region')->nullable();
                $t->string('Client Name')->nullable();
                $t->string('Project Name')->nullable();
                $t->string('Products')->nullable();
                $t->decimal('value_with_vat', 14, 2)->nullable();
                $t->decimal('PO Value', 14, 2)->nullable();
                $t->string('Status')->nullable();
                $t->string('Sales OAA')->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['region','Status','Products']);
                $t->index('date_rec');
                $t->index('so_no');
            });
            return;
        }

        Schema::table('salesorderlog', function (Blueprint $t) {
            if (!Schema::hasColumn('salesorderlog','deleted_at')) $t->softDeletes();
            if (!Schema::hasColumn('salesorderlog','created_at')) $t->timestamps();
        });
    }

    public function down(): void
    {
        // same philosophy: don’t drop production tables automatically
        Schema::table('salesorderlog', function (Blueprint $t) {
            if (Schema::hasColumn('salesorderlog','deleted_at')) $t->dropSoftDeletes();
        });
    }
};
