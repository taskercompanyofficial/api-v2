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
        Schema::create('part_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');

            // Can be requested from Catalog or Inventory Store Item
            $table->foreignId('part_id')->nullable()->constrained('parts')->onDelete('set null');
            $table->foreignId('store_item_id')->nullable()->constrained('store_items')->onDelete('set null');

            // Reserved instance (specific serial/unit)
            $table->foreignId('store_item_instance_id')->nullable()->constrained('store_item_instances')->onDelete('set null');

            $table->integer('quantity')->default(1);
            $table->enum('request_type', ['warranty', 'payable'])->default('payable');

            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);

            $table->enum('status', [
                'requested',    // Just a demand
                'reserved',     // Instance allocated
                'dispatched',   // Out for delivery
                'received',     // Handed to tech
                'used',         // Billed/Installed
                'returned',     // Back to store
                'cancelled'     // No longer needed
            ])->default('requested');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('part_requests');
    }
};
