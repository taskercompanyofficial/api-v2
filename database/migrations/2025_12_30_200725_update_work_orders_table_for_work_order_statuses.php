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
        Schema::table('work_orders', function (Blueprint $table) {
            // Drop old foreign keys if they exist
            if (Schema::hasColumn('work_orders', 'status_id')) {
                $table->dropForeign(['status_id']);
                $table->dropColumn('status_id');
            }
            
            if (Schema::hasColumn('work_orders', 'sub_status_id')) {
                $table->dropForeign(['sub_status_id']);
                $table->dropColumn('sub_status_id');
            }
            
            // Add new foreign keys to work_order_statuses
            $table->foreignId('status_id')
                ->nullable()
                ->after('warranty_expiry_date')
                ->constrained('work_order_statuses')
                ->nullOnDelete();
            
            $table->foreignId('sub_status_id')
                ->nullable()
                ->after('status_id')
                ->constrained('work_order_statuses')
                ->nullOnDelete();
            
            // Add indexes
            $table->index('status_id');
            $table->index('sub_status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Drop work_order_statuses foreign keys
            $table->dropForeign(['status_id']);
            $table->dropForeign(['sub_status_id']);
            $table->dropColumn(['status_id', 'sub_status_id']);
            
            // Restore old statuses foreign keys (optional - depends on your needs)
            $table->foreignId('status_id')->nullable()->constrained('statuses')->onDelete('set null');
            $table->foreignId('sub_status_id')->nullable()->constrained('statuses')->onDelete('set null');
        });
    }
};
