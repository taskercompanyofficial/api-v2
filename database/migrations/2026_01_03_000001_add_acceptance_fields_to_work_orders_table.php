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
            $table->datetime('accepted_at')->nullable()->after('assigned_at');
            $table->datetime('rejected_at')->nullable()->after('accepted_at');
            $table->foreignId('rejected_by')->nullable()->constrained('staff')->onDelete('set null')->after('rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['accepted_at', 'rejected_at', 'rejected_by']);
        });
    }
};
