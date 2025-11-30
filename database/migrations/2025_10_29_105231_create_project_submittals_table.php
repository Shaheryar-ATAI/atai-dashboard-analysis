<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('project_submittals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->string('phase', 32);
            $t->string('file_path');
            $t->string('original_name');
            $t->string('mime', 128)->nullable();
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['project_id','phase']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('project_submittals');
    }
};
