<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('student_uid', 20);
            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10);
            $table->string('blood_group', 5)->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('present_address')->nullable();
            $table->string('permanent_address')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('birth_reg_no', 30)->nullable();
            $table->date('admission_date');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'student_uid']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
