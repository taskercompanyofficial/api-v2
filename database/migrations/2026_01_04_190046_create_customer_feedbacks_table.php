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
        Schema::create('customer_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned()->comment('Rating from 1 to 5');
            $table->enum('feedback_type', ['service_quality', 'technician_behavior', 'timeliness', 'overall'])->default('overall');
            $table->text('remarks')->nullable();
            $table->boolean('is_positive')->default(true)->comment('Auto-calculated: rating >= 4');
            $table->foreignId('created_by')->constrained('staff')->onDelete('cascade');
            $table->foreignId('updated_by')->constrained('staff')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index('work_order_id');
            $table->index('customer_id');
            $table->index('rating');
            $table->index('feedback_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_feedbacks');
    }
};
