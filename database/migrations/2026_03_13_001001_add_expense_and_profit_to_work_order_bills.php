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
        Schema::table('work_order_bills', function (Blueprint $table) {
            $table->decimal('total_expense', 12, 2)->default(0)->after('balance_due');
            $table->decimal('total_profit', 12, 2)->default(0)->after('total_expense');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_order_bills', function (Blueprint $table) {
            $table->dropColumn(['total_expense', 'total_profit']);
        });
    }
};
