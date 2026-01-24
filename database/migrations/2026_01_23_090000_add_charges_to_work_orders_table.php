<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds a charges JSON column to work_orders table for storing
     * quick service charges/billing items directly on the work order.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->json('notes')->nullable()->after('total_amount');
            $table->json('charges')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('charges');
        });
    }
};
