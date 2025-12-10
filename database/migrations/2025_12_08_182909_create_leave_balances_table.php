<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->year('year'); // e.g., 2025
            $table->decimal('total_days', 5, 2)->default(0); // Total allocated
            $table->decimal('used_days', 5, 2)->default(0); // Days used
            $table->decimal('pending_days', 5, 2)->default(0); // Days in pending applications
            $table->decimal('available_days', 5, 2)->default(0); // Remaining days
            $table->timestamps();
            
            // Unique constraint: one balance per staff, leave type, and year
            $table->unique(['staff_id', 'leave_type_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
