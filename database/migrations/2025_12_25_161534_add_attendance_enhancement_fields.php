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
        Schema::table('attendances', function (Blueprint $table) {
            $table->text('late_reason')->nullable()->after('status');
            $table->text('early_leave_reason')->nullable()->after('late_reason');
            $table->boolean('is_manual_checkin')->default(false)->after('early_leave_reason');
            $table->boolean('is_manual_checkout')->default(false)->after('is_manual_checkin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['late_reason', 'early_leave_reason', 'is_manual_checkin', 'is_manual_checkout']);
        });
    }
};
