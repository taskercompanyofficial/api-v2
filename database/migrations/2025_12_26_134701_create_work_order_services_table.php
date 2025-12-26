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
        Schema::create('work_order_services', function (Blueprint $table) {
            $table->id();
            
            // Work Order (REQUIRED)
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            
            // Service Info (Optional - can be added later)
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');
            $table->foreignId('parent_service_id')->nullable()->constrained('parent_services')->onDelete('set null');
            $table->string('service_name')->nullable();
            
            // Service Type (Optional)
            $table->enum('service_type', ['warranty', 'paid', 'free_installation'])->default('paid');
            
            // Pricing (Optional)
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('brand_tariff_price', 10, 2)->nullable();
            $table->decimal('final_price', 10, 2)->default(0);
            
            // Warranty (Optional)
            $table->boolean('is_warranty_covered')->default(false);
            $table->string('warranty_part_used')->nullable();
            
            // Status (Optional)
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            
            // Technician (Optional)
            $table->foreignId('assigned_technician_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->text('technician_notes')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->datetime('completed_at')->nullable();
            
            // Indexes
            $table->index('work_order_id');
            $table->index('parent_service_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_services');
    }
};
