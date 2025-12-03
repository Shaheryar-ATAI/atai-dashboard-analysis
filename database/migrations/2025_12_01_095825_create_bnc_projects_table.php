<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bnc_projects', function (Blueprint $table) {
            $table->id();

            // --------------------------
            // RAW BNC FIELDS
            // --------------------------
            $table->string('reference_no')->index();
            $table->string('project_name');
            $table->string('city')->nullable();
            $table->string('region', 20)->index();   // Eastern / Central / Western (chosen at upload)
            $table->string('country')->default('Saudi Arabia');

            $table->string('stage')->nullable();     // Concept / Tender / Under Construction…
            $table->string('industry')->nullable();

            $table->decimal('value_usd', 18, 2)->nullable();

            $table->date('award_date')->nullable();
            $table->string('client')->nullable();
            $table->string('consultant')->nullable();
            $table->string('main_contractor')->nullable();
            $table->string('mep_contractor')->nullable();

            $table->string('datasets')->nullable();       // last column in export
            $table->string('source_file')->nullable();    // uploaded filename
            $table->dateTime('bnc_exported_at')->nullable();

            // --------------------------
            // SCRAPED FIELDS (from Python)
            // --------------------------
            $table->longText('overview_info')->nullable(); // reference number, sector, industry, type…
            $table->longText('latest_news')->nullable();   // latest update line
            $table->longText('raw_parties')->nullable();   // full consultant + contractor + MEP combined block
            $table->dateTime('scraped_at')->nullable();    // when scraper last updated

            // --------------------------
            // ATAI SALES CHECKPOINTS
            // --------------------------
            $table->boolean('approached')->default(false);

            $table->enum('lead_qualified', ['Hot', 'Warm', 'Cold', 'Unknown'])
                ->default('Unknown');

            $table->unsignedTinyInteger('penetration_percent')->default(0);

            $table->boolean('boq_shared')->default(false);
            $table->boolean('submittal_shared')->default(false);
            $table->boolean('submittal_approved')->default(false);

            $table->date('expected_close_date')->nullable();

            // --------------------------
            // RESPONSIBILITY + NOTES
            // --------------------------
            $table->foreignId('responsible_salesman_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            // --------------------------
            // AUDIT FIELDS
            // --------------------------
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

    public function down(): void
    {
        Schema::dropIfExists('bnc_projects');
    }
};
