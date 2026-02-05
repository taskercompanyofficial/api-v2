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
        Schema::create('staff_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->enum('type', ['advance', 'loan'])->default('advance');
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'partially_paid', 'completed'])->default('pending');
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('remaining_amount', 10, 2)->default(0);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->integer('installments')->default(1)->comment('Number of installments for repayment');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'status']);
            $table->index(['date']);
        });

        // Table to track advance deductions from salary
        Schema::create('staff_advance_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('staff_advances')->onDelete('cascade');
            $table->foreignId('salary_payout_id')->constrained('salary_payouts')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('month'); // e.g., '2026-02'
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['advance_id']);
            $table->index(['salary_payout_id']);
            $table->index(['month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_advance_deductions');
        Schema::dropIfExists('staff_advances');
    }
};
