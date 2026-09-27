<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('combined_key', 50)->nullable();
            $table->decimal('marks', 6, 2);
            $table->decimal('full_marks', 6, 2);
            $table->unsignedTinyInteger('percent');
            $table->string('letter', 3);
            $table->decimal('point', 3, 2);
            $table->boolean('is_pass');
            $table->boolean('is_optional');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_results');
    }
};
