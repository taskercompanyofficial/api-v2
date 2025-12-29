<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores individual service items within an order
     * Each row represents one parent service with its quantity and scheduling
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_service_id')->constrained('parent_services')->onDelete('cascade');
            
            // Service details
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('subtotal', 10, 2); // (unit_price - discount) * quantity
            
            // Scheduling for this specific service
            $table->date('scheduled_date');
            $table->time('scheduled_time');
            $table->dateTime('scheduled_datetime')->virtualAs('CONCAT(scheduled_date, " ", scheduled_time)');
            
            // Service execution tracking
            $table->enum('status', [
                'pending',              // Awaiting scheduling/assignment
                'scheduled',            // Scheduled with date/time
                'assigned',             // Assigned to technician
                'in_progress',          // Service being performed
                'completed',            // Service completed
                'cancelled',            // Service cancelled
                'rescheduled'          // Service rescheduled
            ])->default('pending');
            
            // Technician assignment
            $table->foreignId('assigned_technician_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            
            // Service completion
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->json('completion_images')->nullable();
            
            // Customer feedback
            $table->integer('rating')->nullable(); // 1-5 stars
            $table->text('feedback')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index('parent_service_id');
            $table->index('status');
            $table->index('scheduled_date');
            $table->index('assigned_technician_id');
            $table->index(['order_id', 'status']);
            $table->index(['assigned_technician_id', 'scheduled_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
