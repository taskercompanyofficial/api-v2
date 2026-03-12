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
        Schema::create('vendor_specific_rates', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
            $table->enum('item_type', ['service', 'part']);

            // For Services
            $table->foreignId('parent_service_id')->nullable()->constrained('parent_services')->onDelete('cascade');
            
            // For Parts
            $table->string('part_code', 50)->nullable();

            // Overrides
            $table->decimal('vendor_rate', 15, 2)->nullable(); // Override for service rate
            $table->decimal('revenue_share_percentage', 5, 2)->nullable(); // Override for benefit share

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['vendor_id', 'item_type']);
            $table->index(['vendor_id', 'parent_service_id']);
            $table->index(['vendor_id', 'part_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_specific_rates');
    }
};
