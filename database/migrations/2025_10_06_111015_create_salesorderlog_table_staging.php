<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('salesorderlog')) return;

        Schema::create('salesorderlog', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('po_no', 100)->nullable()->index();
            $table->date('date_rec')->nullable()->index();

            $table->string('region', 32)->nullable()->index(); // Eastern|Central|Western
            $table->string('Client Name', 255)->nullable();    // legacy exact column names kept for compatibility
            $table->string('Project Name', 255)->nullable();
            $table->string('Products', 255)->nullable()->index();

            $table->decimal('value_with_vat', 15, 2)->nullable()->default(0);
            $table->decimal('PO Value', 15, 2)->nullable()->default(0);

            $table->string('Status', 64)->nullable()->index(); // Accepted|Pre-Acceptance|Waiting|Rejected
            $table->string('Sales OAA', 100)->nullable();       // salesperson
            $table->string('Remarks', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesorderlog');
    }
};

