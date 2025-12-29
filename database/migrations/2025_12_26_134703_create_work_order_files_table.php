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
        Schema::create('work_order_files', function (Blueprint $table) {
            $table->id();
            
            // Work Order (REQUIRED)
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            
            // File Info (Optional)
            $table->string('file_name')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_type', 50)->nullable();
            $table->integer('file_size_kb')->nullable();
            $table->string('mime_type', 100)->nullable();
            
            // Upload Info (Optional)
            $table->foreignId('uploaded_by_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->datetime('uploaded_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('work_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_files');
    }
};
