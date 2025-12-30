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
                'name' => 'Allocated',
                'color' => '#3b82f6', // Blue
                'order' => 1,
                'children' => [
                    ['name' => 'Just Launched', 'color' => '#60a5fa', 'order' => 1],
                    ['name' => 'Customer Verification Pending', 'color' => '#93c5fd', 'order' => 2],
                    ['name' => 'Technician Assign Pending', 'color' => '#bfdbfe', 'order' => 3],
                ],
            ],
            [
                'name' => 'Dispatched',
                'color' => '#8b5cf6', // Purple
                'order' => 2,
                'children' => [
                    ['name' => 'Assigned to Technician', 'color' => '#a78bfa', 'order' => 1],
                    ['name' => 'Technician Accept Pending', 'color' => '#c4b5fd', 'order' => 2],
                    ['name' => 'Technician Accepted', 'color' => '#ddd6fe', 'order' => 3],
                ],
            ],
            [
                'name' => 'In Progress',
                'color' => '#f59e0b', // Amber
                'order' => 3,
                'children' => [
                    ['name' => 'Going to Work', 'color' => '#fbbf24', 'order' => 1],
                    ['name' => 'Reached Location', 'color' => '#fcd34d', 'order' => 2],
                    ['name' => 'Work Started', 'color' => '#fde68a', 'order' => 3],
                    ['name' => 'Work In Progress', 'color' => '#fef3c7', 'order' => 4],
                    ['name' => 'Waiting for Parts', 'color' => '#fef9e7', 'order' => 5],
                ],
            ],
            [
                'name' => 'On Hold',
                'color' => '#6b7280', // Gray
                'order' => 4,
                'children' => [
                    ['name' => 'Customer Unavailable', 'color' => '#9ca3af', 'order' => 1],
                    ['name' => 'Parts On Order', 'color' => '#d1d5db', 'order' => 2],
                    ['name' => 'Awaiting Customer Approval', 'color' => '#e5e7eb', 'order' => 3],
                    ['name' => 'Payment Pending', 'color' => '#f3f4f6', 'order' => 4],
                ],
            ],
            [
                'name' => 'Completed',
                'color' => '#10b981', // Green
                'order' => 5,
                'children' => [
                    ['name' => 'Work Completed', 'color' => '#34d399', 'order' => 1],
                    ['name' => 'Successfully Completed', 'color' => '#6ee7b7', 'order' => 2],
                    ['name' => 'Warranty Completed', 'color' => '#a7f3d0', 'order' => 3],
                    ['name' => 'Customer Approved', 'color' => '#d1fae5', 'order' => 4],
                ],
            ],
            [
                'name' => 'Cancelled',
                'color' => '#ef4444', // Red
                'order' => 6,
                'children' => [
                    ['name' => 'Customer Cancelled', 'color' => '#f87171', 'order' => 1],
                    ['name' => 'Technician Rejected', 'color' => '#fca5a5', 'order' => 2],
                    ['name' => 'Unable to Complete', 'color' => '#fecaca', 'order' => 3],
                    ['name' => 'Duplicate Request', 'color' => '#fee2e2', 'order' => 4],
                ],
            ],
            [
                'name' => 'Closed',
                'color' => '#14b8a6', // Teal
                'order' => 7,
                'children' => [
                    ['name' => 'Closed - Completed', 'color' => '#2dd4bf', 'order' => 1],
                    ['name' => 'Closed - Cancelled', 'color' => '#5eead4', 'order' => 2],
                    ['name' => 'Closed - No Action Required', 'color' => '#99f6e4', 'order' => 3],
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
