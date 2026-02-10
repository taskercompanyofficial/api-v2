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
        // Drop dependent tables first to satisfy foreign key constraints
        if (Schema::hasTable('order_status_history')) {
            Schema::dropIfExists('order_status_history');
        }

        if (Schema::hasTable('order_items')) {
            Schema::dropIfExists('order_items');
        }

        if (Schema::hasTable('free_installations')) {
            Schema::dropIfExists('free_installations');
        }

        if (Schema::hasTable('orders')) {
            Schema::dropIfExists('orders');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: legacy tables intentionally removed
        // If rollback is needed, restore from backups or previous migrations
    }
};

