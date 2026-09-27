<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_fee_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);
            $table->decimal('value', 12, 2);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'fee_head_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_waivers');
    }
};
