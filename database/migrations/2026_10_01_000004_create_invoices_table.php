<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('invoice_no', 20);
            $table->char('month', 7)->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('waiver_total', 12, 2)->default(0);
            $table->decimal('fine_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->string('status', 10)->default('unpaid');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'invoice_no']);
            // One invoice per enrollment per month, even if two generate runs overlap.
            $table->unique(['enrollment_id', 'month']);
            $table->index(['school_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
