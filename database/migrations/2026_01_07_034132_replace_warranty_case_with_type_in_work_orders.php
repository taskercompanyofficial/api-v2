<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Add warranty_type_id column
            $table->foreignId('warranty_type_id')
                ->nullable()
                ->after('is_warranty_case')
                ->constrained('warranty_types')
                ->onDelete('set null');

            $table->index('warranty_type_id');
        });

        // Migrate existing data: 
        // is_warranty_case = true  → warranty_type_id = 1 (Warranty)
        // is_warranty_case = false → warranty_type_id = 2 (Paid Service)
        // DB::statement("
        //     UPDATE work_orders 
        //     SET warranty_type_id = CASE 
        //         WHEN is_warranty_case = 1 THEN 1 
        //         WHEN is_warranty_case = 0 THEN 2 
        //         ELSE NULL 
        //     END
        // ");

        Schema::table('work_orders', function (Blueprint $table) {
            // Drop old column
            $table->dropColumn('is_warranty_case');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Add back is_warranty_case
            $table->boolean('is_warranty_case')->default(false)->after('warranty_verified');
        });

        // Migrate data back:
        // warranty_type_id = 1 → is_warranty_case = true
        // warranty_type_id = 2 → is_warranty_case = false
        DB::statement("
            UPDATE work_orders 
            SET is_warranty_case = CASE 
                WHEN warranty_type_id = 1 THEN 1 
                ELSE 0 
            END
        ");

        Schema::table('work_orders', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['warranty_type_id']);
            $table->dropColumn('warranty_type_id');
        });
    }
};
