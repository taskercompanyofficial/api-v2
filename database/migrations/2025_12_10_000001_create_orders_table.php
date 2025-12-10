<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores the main order/booking information
     * One order can have multiple services (stored in order_items table)
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_address_id')->constrained('customer_addresses')->onDelete('cascade');
            
            // Order identification
            $table->string('order_number')->unique();
            $table->date('order_date');
            
            // Customer notes and special instructions
            $table->text('notes')->nullable();
            
            // Payment information
            $table->enum('payment_method', ['cash', 'card', 'wallet'])->default('cash');
            $table->enum('payment_status', ['pending', 'paid', 'partially_paid', 'refunded', 'failed'])->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            
            // Order status
            $table->enum('status', [
                'pending',           // Order placed, awaiting confirmation
                'confirmed',         // Order confirmed
                'scheduled',         // Services scheduled
                'in_progress',       // At least one service is in progress
                'completed',         // All services completed
                'cancelled',         // Order cancelled
                'refunded'          // Order refunded
            ])->default('pending');
            
            // Timestamps
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('order_number');
            $table->index('status');
            $table->index('payment_status');
            $table->index('order_date');
            $table->index(['customer_id', 'status']);
            $table->index(['customer_id', 'order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
