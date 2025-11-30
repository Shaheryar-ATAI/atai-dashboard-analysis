<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('projects')) return;

        Schema::create('projects', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Core business fields (adjust labels to your imports)
            $table->string('name', 255)->index();            // Project name
            $table->string('client', 255)->nullable()->index();
            $table->string('location', 255)->nullable();
            $table->string('area', 32)->nullable()->index(); // Eastern | Central | Western

            $table->string('quotation_no', 100)->nullable()->index();
            $table->date('quotation_date')->nullable();

            $table->string('atai_products', 255)->nullable()->index();
            $table->decimal('quotation_value', 15, 2)->default(0);

            // Status (original), plus cache columns you’ll update from checklist
            $table->string('status', 32)->nullable()->index();          // raw imported status if any
            $table->string('status_current', 32)->nullable()->index();  // derived current status
            $table->unsignedTinyInteger('progress_pct')->default(0)->index();

            // Optional import helpers
            $table->timestamp('date_rec')->nullable(); // if you have it in imports
            $table->string('salesperson', 100)->nullable();

            $table->timestamps();       // created_at/updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
