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
        Schema::table('work_order_services', function (Blueprint $table) {
            if (!Schema::hasColumn('work_order_services', 'quantity')) {
                $table->integer('quantity')->default(1)->after('parent_service_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_order_services', function (Blueprint $table) {
            if (Schema::hasColumn('work_order_services', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });
    }
};
