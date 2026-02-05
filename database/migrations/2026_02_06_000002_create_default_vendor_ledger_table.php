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
        Schema::create('default_vendor_ledger', function (Blueprint $table) {
            $table->id();

            // What this entry is for
            $table->enum('item_type', ['service', 'part']);

            // For Services (linked to parent services)
            $table->foreignId('parent_service_id')->nullable()->constrained('parent_services')->nullOnDelete();
            $table->string('service_name')->nullable();

            // For Parts (general inventory/materials)
            $table->string('part_name')->nullable();
            $table->string('part_code', 50)->nullable()->unique(); // e.g., PIPE-001, GAS-R410A
            $table->string('unit', 50)->nullable(); // e.g., "feet", "kg", "meter", "piece"

            // Pricing
            $table->decimal('vendor_rate', 15, 2)->nullable(); // For services: fixed vendor payment
            $table->decimal('cost_price', 15, 2)->nullable(); // For parts: cost per unit

            // Revenue Sharing (only for parts)
            $table->decimal('revenue_share_percentage', 5, 2)->default(50.00); // Vendor's % of profit

            // Status
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            // Metadata
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('item_type');
            $table->index('is_active');
            $table->index(['item_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('default_vendor_ledger');
    }
};
