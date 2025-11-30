<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('project_notes')) return;

        Schema::create('project_notes', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('project_id');
            $table->text('note');
            $table->unsignedBigInteger('created_by');

            $table->timestamps();

            $table->index('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_notes');
    }
};
