<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add polymorphic columns to order_items table to support multiple item types
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Add polymorphic columns
            $table->unsignedBigInteger('itemable_id')->nullable()->after('order_id');
            $table->string('itemable_type')->nullable()->after('itemable_id');
            
            // Make parent_service_id nullable for backward compatibility
            $table->foreignId('parent_service_id')->nullable()->change();
            
            // Add indexes for polymorphic relationship
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Remove polymorphic columns
            $table->dropIndex(['itemable_type', 'itemable_id']);
            $table->dropColumn(['itemable_id', 'itemable_type']);
            
            // Make parent_service_id required again
            $table->foreignId('parent_service_id')->nullable(false)->change();
        });
    }
};
