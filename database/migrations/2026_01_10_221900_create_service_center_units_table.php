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
        Schema::create('service_center_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');

            // Unit Identification
            $table->string('unit_serial_number')->nullable();
            $table->string('unit_model')->nullable();
            $table->string('unit_type')->nullable(); // AC, Washing Machine, etc.
            $table->text('unit_condition_on_arrival')->nullable();

            // Pickup from Customer
            $table->date('pickup_date')->nullable();
            $table->time('pickup_time')->nullable();
            $table->foreignId('pickup_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->text('pickup_location')->nullable();
            $table->json('pickup_photos')->nullable();

            // At Service Center
            $table->dateTime('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('bay_number')->nullable(); // Location in service center

            // Status
            $table->enum('status', [
                'pending_pickup',
                'in_transit_to_center',
                'received',
                'diagnosis',
                'awaiting_parts',
                'in_repair',
                'repaired',
                'quality_check',
                'ready_for_delivery',
                'in_transit_to_customer',
                'delivered'
            ])->default('pending_pickup');

            // Diagnosis & Repair
            $table->text('diagnosis_notes')->nullable();
            $table->text('repair_notes')->nullable();
            $table->json('parts_used')->nullable();
            $table->dateTime('repair_completed_at')->nullable();
            $table->foreignId('repair_completed_by')->nullable()->constrained('staff')->nullOnDelete();

            // Delivery to Customer
            $table->date('delivery_date')->nullable();
            $table->time('delivery_time')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->json('delivery_photos')->nullable();
            $table->string('customer_signature')->nullable();

            // Tracking
            $table->date('estimated_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_center_units');
    }
};
