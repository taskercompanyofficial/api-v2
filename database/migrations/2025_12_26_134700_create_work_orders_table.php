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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            
            $table->id();
            
            // Work Order Number (REQUIRED - Unique)
            $table->string('work_order_number', 50)->unique();
            
            // Customer Info (REQUIRED)
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('customer_address_id')->constrained('customer_addresses')->onDelete('cascade');
            
            // Foreign Keys for Relationships
            $table->foreignId('authorized_brand_id')->nullable()->constrained('authorized_brands')->onDelete('set null');
            $table->foreignId('branch_id')->nullable()->constrained('our_branches')->onDelete('set null');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');
            $table->foreignId('parent_service_id')->nullable()->constrained('parent_services')->onDelete('set null');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            
            // Work Order Details
            $table->string('brand_complaint_no', 100)->nullable();
            $table->string('work_order_source', 100)->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->string('reject_reason', 255)->nullable();
            $table->string('satisfation_code', 100)->nullable();
            $table->string('without_satisfaction_code_reason', 255)->nullable();
            
            // Service Timing
            $table->date('appointment_date')->nullable();
            $table->time('appointment_time')->nullable();
            $table->date('service_start_date')->nullable();
            $table->time('service_start_time')->nullable();
            $table->date('service_end_date')->nullable();
            $table->time('service_end_time')->nullable();
            
            // Descriptions
            $table->text('customer_description')->nullable();
            $table->text('defect_description')->nullable();
            $table->text('technician_remarks')->nullable();
            $table->text('service_description')->nullable();
            
            // Product Information
            $table->string('product_indoor_model', 100)->nullable();
            $table->string('product_outdoor_model', 100)->nullable();
            $table->string('indoor_serial_number', 100)->nullable()->index();
            $table->string('outdoor_serial_number', 100)->nullable()->index();
            $table->string('warrenty_serial_number', 100)->nullable();
            $table->string('product_model', 100)->nullable();
            $table->date('purchase_date')->nullable();
            
            // Warranty Information
            $table->boolean('is_warranty_case')->default(false);
            $table->boolean('warranty_verified')->default(false)->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->date('warrenty_start_date')->nullable();
            $table->date('warrenty_end_date')->nullable();
            $table->string('warrenty_status', 50)->nullable();
            $table->string('warranty_card_serial', 100)->nullable();
            
            // Status & Assignment
            $table->foreignId('status_id')->nullable()->constrained('statuses')->onDelete('set null');
            $table->foreignId('sub_status_id')->nullable()->constrained('statuses')->onDelete('set null');
            $table->foreignId('assigned_to_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->datetime('assigned_at')->nullable();
            
            // Financial
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'partial', 'refunded', 'waived'])->default('pending');
            
            // Completion & Tracking
            $table->datetime('completed_at')->nullable();
            $table->string('completed_by', 100)->nullable();
            $table->datetime('closed_at')->nullable();
            $table->string('closed_by', 100)->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->string('cancelled_by', 100)->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');
            
            // Soft Delete
            $table->softDeletes();
            
            // Indexes
            $table->index('customer_id');
            $table->index('customer_address_id');
            $table->index('authorized_brand_id');
            $table->index('branch_id');
            $table->index('category_id');
            $table->index('service_id');
            $table->index('parent_service_id');
            $table->index('product_id');
            $table->index('status_id');
            $table->index('sub_status_id');
            $table->index('assigned_to_id');
            $table->index('work_order_number');
            $table->index('created_at');
            $table->index('updated_at');
            $table->index('completed_at');
            $table->index('cancelled_at');
            $table->index('appointment_date');
            $table->index('service_start_date');
            $table->index('service_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
