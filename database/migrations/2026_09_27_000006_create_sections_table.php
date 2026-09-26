<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('version_id')->constrained()->restrictOnDelete();
            $table->string('name', 20);
            $table->unsignedSmallInteger('capacity')->nullable();

            $table->unique(['school_id', 'school_class_id', 'shift_id', 'version_id', 'name'], 'sections_unique_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
