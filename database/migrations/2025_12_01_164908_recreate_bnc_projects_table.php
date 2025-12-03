<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bnc_projects', function (Blueprint $table) {
            $table->id();

            // Raw BNC fields
            $table->string('reference_no')->index();
            $table->string('project_name');
            $table->string('city')->nullable();
            $table->string('region', 20)->index(); // Eastern / Central / Western
            $table->string('country')->default('Saudi Arabia');
            $table->string('stage')->nullable();    // Concept / Tender / Under Construction / etc.
            $table->string('industry')->nullable();
            $table->decimal('value_usd', 18, 2)->nullable();
            $table->date('award_date')->nullable();
            $table->string('client')->nullable();
            $table->string('consultant')->nullable();
            $table->string('main_contractor')->nullable();
            $table->string('mep_contractor')->nullable();
            $table->string('datasets')->nullable();      // last column in BNC export
            $table->string('source_file')->nullable();   // name of Excel/CSV file
            $table->dateTime('bnc_exported_at')->nullable(); // optional: parsed from header

            // 🔎 Deep-scraped text from Selenium script
            $table->longText('overview_info')->nullable();  // Reference No, sector, type, value, GPS, etc.
            $table->longText('latest_news')->nullable();    // last BNC news line
            $table->longText('raw_parties')->nullable();    // full formatted owners/consultants/contractors block
            $table->dateTime('scraped_at')->nullable();     // when scraper processed this project

            // ATAI sales checkpoints (editable by sales)
            $table->boolean('approached')->default(false);
            $table->enum('lead_qualified', ['Hot', 'Warm', 'Cold', 'Unknown'])
                ->default('Unknown');
            $table->unsignedTinyInteger('penetration_percent')->default(0); // 0–100

            $table->boolean('boq_shared')->default(false);
            $table->boolean('submittal_shared')->default(false);
            $table->boolean('submittal_approved')->default(false);

            $table->date('expected_close_date')->nullable();

            // who is responsible inside ATAI
            $table->foreignId('responsible_salesman_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            // audit fields
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bnc_projects');
    }
};
