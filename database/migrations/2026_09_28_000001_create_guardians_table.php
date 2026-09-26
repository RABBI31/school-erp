<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->string('relation', 20);
            $table->string('phone', 20);
            $table->string('alt_phone', 20)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('nid', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
