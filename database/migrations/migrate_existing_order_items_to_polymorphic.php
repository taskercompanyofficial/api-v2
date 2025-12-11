<?php

use App\Models\ParentServices;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Migrate existing parent_service order items to polymorphic structure
     */
    public function up(): void
    {
        // Update existing order items to use polymorphic relationship
        DB::table('order_items')
            ->whereNotNull('parent_service_id')
            ->update([
                'itemable_id' => DB::raw('parent_service_id'),
                'itemable_type' => ParentServices::class
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear polymorphic data (parent_service_id is still there)
        DB::table('order_items')
            ->where('itemable_type', ParentServices::class)
            ->update([
                'itemable_id' => null,
                'itemable_type' => null
            ]);
    }
};
