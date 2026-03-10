<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('vendor_staff_id')->nullable()->after('assigned_vendor_id')->constrained('vendor_staff')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['vendor_staff_id']);
            $table->dropColumn('vendor_staff_id');
        });
    }
};
