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
            $table->id();
            
            // Work Order Number (REQUIRED - Unique)
            $table->string('work_order_number', 50)->unique();
            
            // Customer Info (REQUIRED)
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('customer_address_id')->constrained('customer_addresses')->onDelete('cascade');
            
            // Brand & Reference (Optional)
            $table->foreignId('authorized_brand_id')->nullable()->constrained('authorized_brands')->onDelete('set null');
            $table->string('brand_complaint_no', 100)->nullable();
            
            // Serial Numbers & Product Info (Optional)
            $table->string('indoor_serial_number', 100)->nullable()->index();
            $table->string('outdoor_serial_number', 100)->nullable()->index();
            $table->string('warranty_card_serial', 100)->nullable();
            $table->string('product_model', 100)->nullable();
            
            // Description (REQUIRED)
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->text('customer_description');
            
            // Warranty (Optional)
            $table->boolean('is_warranty_case')->default(false);
            $table->boolean('warranty_verified')->default(false)->nullable();
            $table->date('warranty_expiry_date')->nullable();
            
            // Status & Assignment (Optional)
            $table->foreignId('status_id')->nullable()->constrained('statuses')->onDelete('set null');
            $table->foreignId('sub_status_id')->nullable()->constrained('statuses')->onDelete('set null');
            $table->foreignId('assigned_to_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->datetime('assigned_at')->nullable();
            
            // Financial (Auto-calculated)
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'partial', 'refunded'])->default('pending');
            
            // Timestamps
            $table->timestamps();
            $table->datetime('completed_at')->nullable();
            $table->datetime('cancelled_at')->nullable();
            
            // Audit
             $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');
            // Soft Delete
            $table->softDeletes();
            
            // Indexes
            $table->index('customer_id');
            $table->index('status_id');
            $table->index('assigned_to_id');
            $table->index('created_at');
            $table->index('work_order_number');
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
