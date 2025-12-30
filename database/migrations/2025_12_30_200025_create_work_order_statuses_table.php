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
        Schema::create('work_order_statuses', function (Blueprint $table) {
            $table->id();
            
            // Self-referencing for parent-child hierarchy
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('work_order_statuses')
                ->nullOnDelete();
            
            // Basic information
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Visual customization
            $table->string('color', 7)->default('#64748b'); // Hex color code
            $table->string('icon')->nullable();
            
            // Ordering and status
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('staff')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('parent_id');
            $table->index('is_active');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_statuses');
    }
};
