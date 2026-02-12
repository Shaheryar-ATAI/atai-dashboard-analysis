<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $db)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function addIndexIfMissing(string $table, string $index, string $columnsSql): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `$table` ADD INDEX `$index` ($columnsSql)");
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (!$this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `$table` DROP INDEX `$index`");
    }

    public function up(): void
    {
        if (Schema::hasTable('projects')) {
            $this->addIndexIfMissing('projects', 'idx_projects_deleted_at', '`deleted_at`');
            $this->addIndexIfMissing('projects', 'idx_projects_quotation_no', '`quotation_no`');
            $this->addIndexIfMissing('projects', 'idx_projects_salesman', '`salesman`');
            $this->addIndexIfMissing('projects', 'idx_projects_salesperson', '`salesperson`');
            $this->addIndexIfMissing('projects', 'idx_projects_status', '`status`');
            $this->addIndexIfMissing('projects', 'idx_projects_project_type', '`project_type`');
            $this->addIndexIfMissing('projects', 'idx_projects_area_quotation_date', '`area`, `quotation_date`');
            $this->addIndexIfMissing('projects', 'idx_projects_quotation_date_deleted_at', '`quotation_date`, `deleted_at`');
        }

        if (Schema::hasTable('salesorderlog')) {
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_date_rec', '`date_rec`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_deleted_at', '`deleted_at`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_rejected_at', '`rejected_at`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_sales_source', '`Sales Source`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_project_region', '`project_region`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_status', '`Status`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_po_no', '`PO. No.`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_quote_no', '`Quote No.`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_sales_source_date', '`Sales Source`, `date_rec`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_region_date', '`project_region`, `date_rec`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_status_date', '`Status`, `date_rec`');
            $this->addIndexIfMissing('salesorderlog', 'idx_sol_date_deleted_at', '`date_rec`, `deleted_at`');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects')) {
            $this->dropIndexIfExists('projects', 'idx_projects_deleted_at');
            $this->dropIndexIfExists('projects', 'idx_projects_quotation_no');
            $this->dropIndexIfExists('projects', 'idx_projects_salesman');
            $this->dropIndexIfExists('projects', 'idx_projects_salesperson');
            $this->dropIndexIfExists('projects', 'idx_projects_status');
            $this->dropIndexIfExists('projects', 'idx_projects_project_type');
            $this->dropIndexIfExists('projects', 'idx_projects_area_quotation_date');
            $this->dropIndexIfExists('projects', 'idx_projects_quotation_date_deleted_at');
        }

        if (Schema::hasTable('salesorderlog')) {
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_date_rec');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_deleted_at');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_rejected_at');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_sales_source');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_project_region');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_status');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_po_no');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_quote_no');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_sales_source_date');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_region_date');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_status_date');
            $this->dropIndexIfExists('salesorderlog', 'idx_sol_date_deleted_at');
        }
    }
};
