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
            // Add concern fields after parent_service_id
            $table->foreignId('service_concern_id')->nullable()->after('parent_service_id')
                ->constrained('service_concerns')->onDelete('set null');
            $table->foreignId('service_sub_concern_id')->nullable()->after('service_concern_id')
                ->constrained('service_sub_concerns')->onDelete('set null');

            // Add indexes
            $table->index('service_concern_id');
            $table->index('service_sub_concern_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['service_concern_id']);
            $table->dropForeign(['service_sub_concern_id']);
            $table->dropColumn(['service_concern_id', 'service_sub_concern_id']);
        });
    }
};
