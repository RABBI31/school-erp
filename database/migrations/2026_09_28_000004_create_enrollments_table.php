<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('roll');
            $table->foreignId('optional_subject_id')->nullable()->constrained('subjects')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
            $table->unique(['section_id', 'academic_year_id', 'roll']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
