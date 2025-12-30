<?php

namespace Database\Seeders;

use App\Models\WorkOrderStatus;
use Illuminate\Database\Seeder;

class WorkOrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'New',
                'color' => '#3b82f6', // Blue
                'order' => 1,
                'children' => [
                    ['name' => 'Pending Assignment', 'color' => '#60a5fa', 'order' => 1],
                    ['name' => 'Under Review', 'color' => '#93c5fd', 'order' => 2],
                ],
            ],
            [
                'name' => 'Assigned',
                'color' => '#8b5cf6', // Purple
                'order' => 2,
                'children' => [
                    ['name' => 'Technician Assigned', 'color' => '#a78bfa', 'order' => 1],
                    ['name' => 'Scheduled', 'color' => '#c4b5fd', 'order' => 2],
                ],
            ],
            [
                'name' => 'In Progress',
                'color' => '#f59e0b', // Amber
                'order' => 3,
                'children' => [
                    ['name' => 'En Route', 'color' => '#fbbf24', 'order' => 1],
                    ['name' => 'On Site', 'color' => '#fcd34d', 'order' => 2],
                    ['name' => 'Waiting for Parts', 'color' => '#fde68a', 'order' => 3],
                    ['name' => 'Work In Progress', 'color' => '#fef3c7', 'order' => 4],
                ],
            ],
            [
                'name' => 'On Hold',
                'color' => '#6b7280', // Gray
                'order' => 4,
                'children' => [
                    ['name' => 'Customer Unavailable', 'color' => '#9ca3af', 'order' => 1],
                    ['name' => 'Parts On Order', 'color' => '#d1d5db', 'order' => 2],
                    ['name' => 'Awaiting Approval', 'color' => '#e5e7eb', 'order' => 3],
                ],
            ],
            [
                'name' => 'Completed',
                'color' => '#10b981', // Green
                'order' => 5,
                'children' => [
                    ['name' => 'Successfully Completed', 'color' => '#34d399', 'order' => 1],
                    ['name' => 'Warranty Completed', 'color' => '#6ee7b7', 'order' => 2],
                    ['name' => 'Customer Approved', 'color' => '#a7f3d0', 'order' => 3],
                ],
            ],
            [
                'name' => 'Cancelled',
                'color' => '#ef4444', // Red
                'order' => 6,
                'children' => [
                    ['name' => 'Customer Cancelled', 'color' => '#f87171', 'order' => 1],
                    ['name' => 'Unable to Complete', 'color' => '#fca5a5', 'order' => 2],
                    ['name' => 'Duplicate Request', 'color' => '#fecaca', 'order' => 3],
                ],
            ],
        ];

        foreach ($statuses as $statusData) {
            $children = $statusData['children'] ?? [];
            unset($statusData['children']);

            // Create parent status
            $parentStatus = WorkOrderStatus::create([
                'name' => $statusData['name'],
                'slug' => \Str::slug($statusData['name']),
                'color' => $statusData['color'],
                'order' => $statusData['order'],
                'is_active' => true,
            ]);

            // Create child statuses
            foreach ($children as $childData) {
                WorkOrderStatus::create([
                    'parent_id' => $parentStatus->id,
                    'name' => $childData['name'],
                    'slug' => \Str::slug($childData['name']),
                    'color' => $childData['color'],
                    'order' => $childData['order'],
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Work order statuses seeded successfully!');
    }
}
