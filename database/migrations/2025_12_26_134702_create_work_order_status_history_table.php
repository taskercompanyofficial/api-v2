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
        Schema::create('work_order_status_history', function (Blueprint $table) {
            $table->id();
            
            // Work Order (REQUIRED)
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            
            // Status Change (Optional)
            $table->foreignId('from_status_id')->nullable()->constrained('statuses')->onDelete('set null');
            $table->foreignId('to_status_id')->nullable()->constrained('statuses')->onDelete('set null');
            $table->foreignId('from_sub_status_id')->nullable()->constrained('statuses')->onDelete('set null');
            $table->foreignId('to_sub_status_id')->nullable()->constrained('statuses')->onDelete('set null');
            
            // Change Info (Optional)
            $table->text('notes')->nullable();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('changed_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('work_order_id');
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_status_history');
    }
};
