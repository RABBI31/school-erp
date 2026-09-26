<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name_en', 100);
            $table->string('name_bn', 100);
            $table->string('code', 20);
            $table->string('combined_key', 50)->nullable();
            $table->boolean('is_optional_allowed')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->unique(['school_id', 'school_class_id', 'code']);
            $table->index(['school_class_id', 'display_order']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
