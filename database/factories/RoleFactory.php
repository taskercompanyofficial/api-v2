<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Pre-defined list of HR & CRM roles with descriptions.
     */
    protected static array $roleMap = [
        // HR Management
        ['name' => 'Super Admin',           'slug' => 'super-admin',           'description' => 'Full system access, can manage every module and setting.'],
        ['name' => 'HR Manager',            'slug' => 'hr-manager',            'description' => 'Manages all HR operations: employees, leave, payroll, policies.'],
        ['name' => 'HR Executive',          'slug' => 'hr-executive',            'description' => 'Handles day-to-day HR tasks like onboarding, attendance, records.'],
        ['name' => 'Recruiter',             'slug' => 'recruiter',             'description' => 'Manes job postings, applications, interviews and hiring pipeline.'],
        ['name' => 'Payroll Officer',       'slug' => 'payroll-officer',         'description' => 'Processes payroll, salaries, tax, benefits and compensation.'],
        ['name' => 'Leave Manager',         'slug' => 'leave-manager',           'description' => 'Approves/rejects leave requests and maintains leave records.'],
        ['name' => 'Employee',              'slug' => 'employee',                'description' => 'Standard employee accessing self-service and personal info.'],
        ['name' => 'Team Lead',             'slug' => 'team-lead',               'description' => 'Supervises a team, can view team attendance, leaves, performance.'],
        ['name' => 'Training Manager',      'slug' => 'training-manager',        'description' => 'Plans, schedules and tracks employee training & development.'],
        ['name' => 'Compliance Officer',    'slug' => 'compliance-officer',      'description' => 'Ensures company policies meet labor laws and statutory rules.'],

        // CRM
        ['name' => 'CRM Admin',             'slug' => 'crm-admin',               'description' => 'Configures CRM modules, workflows, custom fields and rights.'],
        ['name' => 'Sales Manager',         'slug' => 'sales-manager',           'description' => 'Oversees sales teams, forecasts, targets and pipelines.'],
        ['name' => 'Sales Representative',  'slug' => 'sales-representative',    'description' => 'Manages leads, opportunities, quotes and closes deals.'],
        ['name' => 'Customer Support Manager', 'slug' => 'support-manager',      'description' => 'Manages support team, tickets, SLAs and customer satisfaction.'],
        ['name' => 'Support Agent',         'slug' => 'support-agent',           'description' => 'Handles customer tickets, live chat and resolution.'],
        ['name' => 'Marketing Manager',     'slug' => 'marketing-manager',       'description' => 'Plans campaigns, tracks leads, analyses ROI and brand reach.'],
        ['name' => 'Marketing Executive',   'slug' => 'marketing-executive',     'description' => 'Executes campaigns, emails, social posts and event coordination.'],
        ['name' => 'Account Manager',       'slug' => 'account-manager',         'description' => 'Maintains client relationships, upsells and ensures renewals.'],
        ['name' => 'Product Manager',       'slug' => 'product-manager',         'description' => 'Defines CRM features, roadmap and user feedback integration.'],
        ['name' => 'Finance Manager',       'slug' => 'finance-manager',         'description' => 'Checks invoices, payments, revenue reports and financial forecasts.'],
    ];

    /**
     * Current index to cycle through predefined roles.
     */
    protected static int $index = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = static::$roleMap[static::$index % count(static::$roleMap)];
        static::$index++;

        return [
            'created_by' => 1,
            'updated_by' => 1,
            'name'        => $role['name'],
            'slug'        => $role['slug'],
            'description' => $role['description'],
            'status'      => $this->faker->boolean(80), // 80% chance of being true (active)
        ];
    }
}
