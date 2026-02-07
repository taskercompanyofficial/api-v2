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
        // Expense Categories (Food, Transport, Fuel, etc.)
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Staff Allowance Configuration
        Schema::create('staff_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->decimal('amount_per_day', 10, 2)->default(0);
            $table->enum('calculation_type', ['attendance', 'weekly', 'monthly', 'salary_percentage'])->default('attendance');
            // For salary_percentage type, this is the percentage
            $table->decimal('percentage', 5, 2)->nullable();
            // Check attendance before allocating (for attendance type)
            $table->boolean('requires_attendance')->default(true);
            // Require CRM access for this allowance
            $table->boolean('requires_crm_access')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();

            // Unique constraint: One allowance per category per staff
            $table->unique(['staff_id', 'expense_category_id'], 'staff_category_unique');
        });

        // Weekly Expense Records
        Schema::create('staff_weekly_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('staff_allowance_id')->nullable()->constrained('staff_allowances')->nullOnDelete();
            $table->date('week_start_date'); // Monday of the week
            $table->date('week_end_date');   // Sunday of the week
            $table->integer('working_days')->default(0);       // Actual working days (based on attendance)
            $table->integer('days_expected')->default(6);      // Expected working days (e.g., 6 = Mon-Sat)
            $table->integer('days_present')->default(0);       // Days staff was present
            $table->integer('days_absent')->default(0);        // Days staff was absent
            $table->integer('days_leave')->default(0);         // Days staff was on approved leave
            $table->decimal('amount_per_day', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0); // days_present * amount_per_day
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();

            // Unique constraint: One expense per staff per category per week
            $table->unique(['staff_id', 'expense_category_id', 'week_start_date'], 'staff_expense_week_unique');
        });

        // Bulk Weekly Expense Summary (for quick reference/reporting)
        Schema::create('weekly_expense_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('our_branches')->nullOnDelete();
            $table->integer('total_staff')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->integer('total_days_paid')->default(0);
            $table->enum('status', ['generated', 'approved', 'paid'])->default('generated');
            $table->foreignId('generated_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['week_start_date', 'expense_category_id', 'branch_id'], 'summary_week_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_expense_summaries');
        Schema::dropIfExists('staff_weekly_expenses');
        Schema::dropIfExists('staff_allowances');
        Schema::dropIfExists('expense_categories');
    }
};
