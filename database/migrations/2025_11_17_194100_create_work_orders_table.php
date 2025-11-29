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
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_asset_id')->constrained('customer_assets')->onDelete('cascade');
            $table->foreignId('customer_address_id')->constrained('customer_addresses')->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('work_order_number')->unique();
            $table->date('work_order_date');
            $table->date('work_order_due_date');
            $table->date('work_order_completed_date')->nullable();
            $table->text('work_order_description');
            $table->text('notes')->nullable();
            $table->json('images')->nullable();
            $table->string('indoor_serial')->nullable();
            $table->string('outdoor_serial')->nullable();
            $table->enum('status', [
                'dispatched', 
                'pending', 
                'technician_accepted', 
                'in_progress', 
                'pending_technician_completion', 
                'pending_technician_verification', 
                'pending_service_center_approval', 
                'awaiting_feedback', 
                'quality_check_pending', 
                'completed', 
                'cancelled'
            ])->default('pending');
            $table->enum('sub_status', [
                'scheduled', 
                'technician_assigned', 
                'technician_en_route', 
                'technician_on_site', 
                'parts_ordered', 
                'awaiting_parts', 
                'maintenance', 
                'repair', 
                'installation', 
                'inspection', 
                'follow_up_required',
                'waiting_customer_response',
                'quality_check',
                'customer_feedback_pending',
                'service_center_review',
                'billing_pending',
                'warranty_verification',
                'pending_via_technician'
            ])->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            // Add indexes for better query performance
            $table->index('work_order_number');
            $table->index('status');
            $table->index('work_order_date');
            $table->index('work_order_completed_date');
            $table->index('work_order_due_date');
            $table->index(['customer_id', 'status']);
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
