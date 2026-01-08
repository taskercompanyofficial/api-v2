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
        Schema::table("work_order_files", function (Blueprint $table) {
            $table->string("approval_status")->default("pending")->after("file_path");
            $table->string("approval_remark")->nullable()->after("approval_status");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("work_order_files", function (Blueprint $table) {
            $table->dropColumn("approval_status");
            $table->dropColumn("approval_remark");
        });
    }
};
