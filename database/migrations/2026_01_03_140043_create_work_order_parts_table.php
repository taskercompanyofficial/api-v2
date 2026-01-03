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
        Schema::create('work_order_parts', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('part_id')->constrained()->restrictOnDelete();
            
            $blueprint->integer('quantity')->default(1);
            
            // Demand Type: Warranty vs Payable
            $blueprint->enum('request_type', ['warranty', 'payable'])->default('payable');
            
            // Pricing details
            $blueprint->enum('pricing_source', ['none', 'local', 'brand'])->default('none');
            $blueprint->decimal('unit_price', 15, 2)->default(0);
            $blueprint->decimal('total_price', 15, 2)->default(0);
            
            // Warranty / Tracking details
            $blueprint->string('part_request_number')->nullable();
            $blueprint->boolean('is_returned_faulty')->default(false);
            $blueprint->timestamp('faulty_part_returned_at')->nullable();
            
            // Workflow Status
            $blueprint->enum('status', [
                'requested',     // Initial demand
                'dispatched',    // Sent from warehouse
                'received',      // Received by technician
                'installed',     // Applied to the unit
                'returned',      // Sent back if not used
                'cancelled'      // Demand cancelled
            ])->default('requested');
            
            $blueprint->text('notes')->nullable();
            
            // Audit fields
            $blueprint->foreignId('created_by')->nullable()->constrained('staff')->nullOnDelete();
            $blueprint->foreignId('updated_by')->nullable()->constrained('staff')->nullOnDelete();
            
            $blueprint->timestamps();
            $blueprint->softDeletes();
            
            // Indexes for faster lookups
            $blueprint->index('part_request_number');
            $blueprint->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_parts');
    }
};
