<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salary_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->string('month'); // format Y-m

            // Financial Details
            $table->decimal('base_salary', 15, 2);
            $table->decimal('daily_rate', 15, 2);
            $table->integer('total_days');
            $table->decimal('effective_days', 8, 2);

            // Adjustments
            $table->integer('relief_absents')->default(0);
            $table->decimal('manual_deduction', 15, 2)->default(0);
            $table->decimal('advance_adjustment', 15, 2)->default(0);
            $table->string('notes')->nullable();

            // Final Amounts
            $table->decimal('calculated_deduction', 15, 2);
            $table->decimal('final_payable', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);

            // Payment Details
            $table->string('status')->default('posted'); // posted, paid, cancelled
            $table->string('transaction_id')->nullable();
            $table->string('payment_proof')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('staff');
            $table->foreignId('updated_by')->nullable()->constrained('staff');
            $table->timestamps();

            // Ensure unique staff per month
            $table->unique(['staff_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_payouts');
    }
};
