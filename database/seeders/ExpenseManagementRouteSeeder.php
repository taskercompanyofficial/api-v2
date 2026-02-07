<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Route;
use Illuminate\Database\Seeder;

class ExpenseManagementRouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create permissions
        $permissionsData = [
            'manage-expense-management' => 'Manage Expense Management',
            'view-staff-allowances' => 'View Staff Allowances',
            'view-weekly-expenses' => 'View Weekly Expenses',
        ];

        $permissions = [];
        foreach ($permissionsData as $slug => $name) {
            $permissions[$slug] = Permission::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $name,
                'description' => 'Permission to ' . strtolower($name),
                'status' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ]);
        }

        // 2. Create Parent Route
        $parent = Route::firstOrCreate(
            ['slug' => 'crm-expense-management'],
            [
                'name' => 'Expense Management',
                'path' => '/',
                'icon' => 'Banknotes',
                'order' => 5,
                'parent_id' => null,
                'permission_id' => $permissions['manage-expense-management']->id,
                'created_by' => 1,
                'updated_by' => 1,
                'description' => 'Staff expense management system',
                'status' => true,
            ]
        );

        // 3. Create Child Routes
        Route::firstOrCreate(
            ['slug' => 'crm-expense-management-allowances'],
            [
                'name' => 'Staff Allowances',
                'path' => '/crm/expense-management/allowances',
                'icon' => 'UserPlus',
                'order' => 1,
                'parent_id' => $parent->id,
                'permission_id' => $permissions['view-staff-allowances']->id,
                'created_by' => 1,
                'updated_by' => 1,
                'description' => 'Manage staff daily allowance settings',
                'status' => true,
            ]
        );

        Route::firstOrCreate(
            ['slug' => 'crm-expense-management-weekly-expenses'],
            [
                'name' => 'Weekly Expenses',
                'path' => '/crm/expense-management/staff-expense',
                'icon' => 'TableCells',
                'order' => 2,
                'parent_id' => $parent->id,
                'permission_id' => $permissions['view-weekly-expenses']->id,
                'created_by' => 1,
                'updated_by' => 1,
                'description' => 'View and generate weekly staff expenses',
                'status' => true,
            ]
        );

        // Also assign these permissions to the Super Admin role (assumed to be ID 1)
        // or just let the user handle it through the UI later. 
        // For development, I'll assign them to role ID 1.
        $role = \App\Models\Role::find(1);
        if ($role) {
            foreach ($permissions as $permission) {
                $role->permissions()->syncWithoutDetaching([$permission->id => ['status' => true]]);
            }
        }
    }
}
