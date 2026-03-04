<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StaffTask;
use App\Models\Staff;

class StaffTaskSeeder extends Seeder
{
    public function run(): void
    {
        $staffIds = Staff::pluck('id')->toArray();
        if (empty($staffIds)) {
            $this->command->warn('No staff found. Skipping task seeding.');
            return;
        }

        // Get a random admin to be the assigner
        $adminId = $staffIds[0] ?? null;

        $tasks = [
            [
                'title' => 'Complete daily service report',
                'description' => 'Submit the end-of-day service report with all completed work orders and customer feedback.',
                'priority' => 'high',
                'category' => 'Reporting',
                'status' => 'pending',
                'due_date' => now()->addDay(),
            ],
            [
                'title' => 'Inventory check for spare parts',
                'description' => 'Count and verify all spare parts in the service van. Report any shortages.',
                'priority' => 'medium',
                'category' => 'Inventory',
                'status' => 'in_progress',
                'due_date' => now()->addDays(2),
            ],
            [
                'title' => 'Attend safety training session',
                'description' => 'Mandatory safety training at the main office. Bring your ID and safety gear.',
                'priority' => 'urgent',
                'category' => 'Training',
                'status' => 'pending',
                'due_date' => now()->addDays(3),
            ],
            [
                'title' => 'Update customer contact information',
                'description' => 'Verify and update contact details for recently serviced customers.',
                'priority' => 'low',
                'category' => 'Admin',
                'status' => 'completed',
                'due_date' => now()->subDay(),
                'completed_at' => now(),
            ],
            [
                'title' => 'Clean and organize service tools',
                'description' => 'Ensure all tools are clean, organized, and in working condition before next shift.',
                'priority' => 'medium',
                'category' => 'Maintenance',
                'status' => 'pending',
                'due_date' => now()->addDays(5),
            ],
        ];

        // Assign tasks to each staff member
        foreach ($staffIds as $staffId) {
            foreach ($tasks as $task) {
                StaffTask::create(array_merge($task, [
                    'staff_id' => $staffId,
                    'assigned_by' => $adminId,
                ]));
            }
        }

        $this->command->info('Staff tasks seeded successfully.');
    }
}
