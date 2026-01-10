<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get max order value
        $maxOrder = DB::table('work_order_statuses')->whereNull('parent_id')->max('order') ?? 0;

        // Insert "Unit in Service Center" parent status
        $parentId = DB::table('work_order_statuses')->insertGetId([
            'name' => 'Unit in Service Center',
            'slug' => 'unit-in-service-center',
            'color' => '#9333EA', // Purple
            'description' => 'Customer unit is at service center for repair',
            'parent_id' => null,
            'order' => $maxOrder + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert sub-statuses
        $subStatuses = [
            ['name' => 'Pending Pickup', 'slug' => 'pending-pickup', 'color' => '#F59E0B', 'order' => 1],
            ['name' => 'In Transit to Center', 'slug' => 'in-transit-to-center', 'color' => '#3B82F6', 'order' => 2],
            ['name' => 'Received at Center', 'slug' => 'received-at-center', 'color' => '#10B981', 'order' => 3],
            ['name' => 'Under Diagnosis', 'slug' => 'under-diagnosis', 'color' => '#8B5CF6', 'order' => 4],
            ['name' => 'Awaiting Parts', 'slug' => 'awaiting-parts', 'color' => '#EF4444', 'order' => 5],
            ['name' => 'In Repair', 'slug' => 'in-repair', 'color' => '#F97316', 'order' => 6],
            ['name' => 'Repaired', 'slug' => 'repaired', 'color' => '#22C55E', 'order' => 7],
            ['name' => 'Quality Check', 'slug' => 'quality-check', 'color' => '#06B6D4', 'order' => 8],
            ['name' => 'Ready for Delivery', 'slug' => 'ready-for-delivery', 'color' => '#84CC16', 'order' => 9],
            ['name' => 'In Transit to Customer', 'slug' => 'in-transit-to-customer', 'color' => '#3B82F6', 'order' => 10],
            ['name' => 'Delivered', 'slug' => 'delivered', 'color' => '#10B981', 'order' => 11],
        ];

        foreach ($subStatuses as $subStatus) {
            DB::table('work_order_statuses')->insert([
                'name' => $subStatus['name'],
                'slug' => $subStatus['slug'],
                'color' => $subStatus['color'],
                'description' => null,
                'parent_id' => $parentId,
                'order' => $subStatus['order'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get parent status ID
        $parent = DB::table('work_order_statuses')->where('slug', 'unit-in-service-center')->first();

        if ($parent) {
            // Delete sub-statuses first
            DB::table('work_order_statuses')->where('parent_id', $parent->id)->delete();
            // Delete parent
            DB::table('work_order_statuses')->where('id', $parent->id)->delete();
        }
    }
};
