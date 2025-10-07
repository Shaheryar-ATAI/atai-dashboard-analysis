<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If users already exists, don't try to create it again.
        if (Schema::hasTable('users')) {
            // OPTIONAL: add any missing columns here instead of creating the table
            Schema::table('users', function (Blueprint $t) {
                if (!Schema::hasColumn('users','created_at')) $t->timestamps();
                // if you want soft deletes on users:
                if (!Schema::hasColumn('users','deleted_at')) $t->softDeletes();
                // add other columns you might need, guarded by Schema::hasColumn(...)
            });
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Do NOT drop in production automatically:
        // Schema::dropIfExists('users');
    }
};
