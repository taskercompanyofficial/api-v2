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
        Schema::table('work_order_files', function (Blueprint $table) {
            $table->foreignId('file_type_id')->nullable()->after('work_order_id')->constrained('file_types')->onDelete('set null');
            $table->index('file_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_order_files', function (Blueprint $table) {
            $table->dropForeign(['file_type_id']);
            $table->dropIndex(['file_type_id']);
            $table->dropColumn('file_type_id');
        });
    }
};
