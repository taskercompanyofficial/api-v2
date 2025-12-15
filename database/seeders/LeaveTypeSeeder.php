<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypes = [
            [
                'name' => 'Annual Leave',
                'code' => 'AL',
                'description' => 'Paid annual vacation leave for rest and recreation',
                'days_per_year' => 20,
                'requires_approval' => true,
                'is_paid' => true,
                'color' => '#4CAF50',
                'is_active' => true,
            ],
            [
                'name' => 'Sick Leave',
                'code' => 'SL',
                'description' => 'Paid leave for medical illness or health-related issues',
                'days_per_year' => 10,
                'requires_approval' => true,
                'is_paid' => true,
                'color' => '#FF9800',
                'is_active' => true,
            ],
            [
                'name' => 'Casual Leave',
                'code' => 'CL',
                'description' => 'Short-term leave for personal matters or emergencies',
                'days_per_year' => 5,
                'requires_approval' => true,
                'is_paid' => true,
                'color' => '#2196F3',
                'is_active' => true,
            ],
            [
                'name' => 'Maternity Leave',
                'code' => 'ML',
                'description' => 'Leave for expecting mothers before and after childbirth',
                'days_per_year' => 90,
                'requires_approval' => true,
                'is_paid' => true,
                'color' => '#E91E63',
                'is_active' => true,
            ],
            [
                'name' => 'Paternity Leave',
                'code' => 'PL',
                'description' => 'Leave for new fathers to support their family',
                'days_per_year' => 7,
                'requires_approval' => true,
                'is_paid' => true,
                'color' => '#9C27B0',
                'is_active' => true,
            ],
            [
                'name' => 'Bereavement Leave',
                'code' => 'BL',
                'description' => 'Leave for mourning the death of a close family member',
                'days_per_year' => 3,
                'requires_approval' => true,
                'is_paid' => true,
                'color' => '#607D8B',
                'is_active' => true,
            ],
            [
                'name' => 'Unpaid Leave',
                'code' => 'UL',
                'description' => 'Leave without pay for personal reasons',
                'days_per_year' => 30,
                'requires_approval' => true,
                'is_paid' => false,
                'color' => '#9E9E9E',
                'is_active' => true,
            ],
            [
                'name' => 'Study Leave',
                'code' => 'STL',
                'description' => 'Leave for educational purposes or professional development',
                'days_per_year' => 10,
                'requires_approval' => true,
                'is_paid' => true,
                'color' => '#00BCD4',
                'is_active' => true,
            ],
            [
                'name' => 'Compensatory Leave',
                'code' => 'COMP',
                'description' => 'Leave earned for working overtime or on holidays',
                'days_per_year' => 15,
                'requires_approval' => true,
                'is_paid' => true,
                'color' => '#FF5722',
                'is_active' => true,
            ],
            [
                'name' => 'Marriage Leave',
                'code' => 'MAR',
                'description' => 'Leave for staff getting married',
                'days_per_year' => 3,
                'requires_approval' => true,
                'is_paid' => true,
                'color' => '#F44336',
                'is_active' => true,
            ],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::updateOrCreate(
                ['code' => $leaveType['code']],
                $leaveType
            );
        }

        $this->command->info('Leave types seeded successfully!');
    }
}
