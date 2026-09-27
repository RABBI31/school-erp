<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->decimal('total_marks', 7, 2);
            $table->decimal('gpa', 3, 2);
            $table->decimal('gpa_without_optional', 3, 2);
            $table->string('letter', 3);
            $table->unsignedSmallInteger('fail_count');
            $table->unsignedSmallInteger('merit_position_section')->nullable();
            $table->unsignedSmallInteger('merit_position_class')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
