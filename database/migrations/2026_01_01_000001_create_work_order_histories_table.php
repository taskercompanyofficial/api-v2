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
        Schema::create('work_order_histories', function (Blueprint $table) {
            $table->id();
            
            // Work Order Reference
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            
            // Action Type
            $table->string('action_type'); // created, updated, status_changed, assigned, file_uploaded, file_deleted, etc.
            
            // Field Changes (JSON for flexibility)
            $table->string('field_name')->nullable(); // Which field was changed
            $table->text('old_value')->nullable(); // Previous value
            $table->text('new_value')->nullable(); // New value
            
            // Additional Context
            $table->json('metadata')->nullable(); // Any additional data (file info, status details, etc.)
            $table->text('description')->nullable(); // Human-readable description
            
            // User Info
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_name')->nullable(); // Store name in case user is deleted
            
            // IP and User Agent for audit
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('work_order_id');
            $table->index('action_type');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_histories');
    }
};
