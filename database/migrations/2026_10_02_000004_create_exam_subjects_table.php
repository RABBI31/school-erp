<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained('classes')->restrictOnDelete();
            $table->decimal('cq_full', 5, 2)->nullable();
            $table->decimal('cq_pass', 5, 2)->nullable();
            $table->decimal('mcq_full', 5, 2)->nullable();
            $table->decimal('mcq_pass', 5, 2)->nullable();
            $table->decimal('practical_full', 5, 2)->nullable();
            $table->decimal('practical_pass', 5, 2)->nullable();
            $table->decimal('total_full', 5, 2);
            $table->decimal('total_pass', 5, 2);
            $table->timestamps();

            $table->unique(['exam_id', 'subject_id']);
            $table->index(['exam_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subjects');
    }
};
