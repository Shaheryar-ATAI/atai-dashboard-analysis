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
        Schema::table('bnc_projects', function (Blueprint $table) {

            // deep scraped text blocks
            $table->longText('overview_info')->nullable()->after('datasets');
            $table->longText('latest_news')->nullable()->after('overview_info');

            // combined consultants + contractors + mep groups (formatted)
            $table->longText('raw_parties')->nullable()->after('latest_news');

            // timestamp when scraper processed it
            $table->dateTime('scraped_at')->nullable()->after('raw_parties');
        });
    }

    public function down(): void
    {
        Schema::table('bnc_projects', function (Blueprint $table) {
            $table->dropColumn([
                'overview_info',
                'latest_news',
                'raw_parties',
                'scraped_at',
            ]);
        });
    }
};
