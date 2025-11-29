<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Route;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions based on actual frontend routes
        $permissions = $this->createPermissions();

        // Create main navigation routes based on frontend structure
        $this->createMainRoutes($permissions);
    }

    /**
     * Create all permissions for the frontend routes
     *
     * @return array
     */
    private function createPermissions(): array
    {
        $permissionsData = [
            // Authentication & Public
            'view-home' => 'View Home Page',
            'view-campaign' => 'View Campaign Page',

            // CRM Dashboard
            'view-crm-dashboard' => 'View CRM Dashboard',

            // User Management
            'manage-users' => 'Manage Users',
            'create-users' => 'Create Users',
            'view-users' => 'View Users',
            'edit-users' => 'Edit Users',
            'delete-users' => 'Delete Users',

            // Staff Management
            'manage-staff' => 'Manage Staff',
            'create-staff' => 'Create Staff',
            'view-staff' => 'View Staff',
            // Settings Management
            'manage-settings' => 'Manage Settings',
            'view-profile' => 'View Profile Settings',
            'manage-appearance' => 'Manage Appearance Settings',
            'manage-advanced-settings' => 'Manage Advanced Settings',
            'manage-routes-settings' => 'Manage Routes Settings',

            // Permission Management
            'manage-permissions' => 'Manage Permissions',
            'manage-roles' => 'Manage Roles',
            'manage-route-permissions' => 'Manage Route Permissions',
            'manage-user-permissions' => 'Manage User Permissions',


            // Support & Feedback
            'view-support' => 'View Support',
            'submit-feedback' => 'Submit Feedback',
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

        return $permissions;
    }

    /**
     * Create main routes based on frontend structure
     *
     * @param array $permissions
     */
    private function createMainRoutes(array $permissions): void
    {
        // Home & Public Routes
        $this->createRoute([
            'name' => 'Home',
            'slug' => 'home',
            'path' => '/',
            'icon' => 'Home',
            'order' => 1,
            'permission_id' => $permissions['view-home']->id,
        ]);

        // CRM Users Management
        $users = $this->createRoute([
            'name' => 'Users',
            'slug' => 'crm-users',
            'path' => '/',
            'icon' => 'Users',
            'order' => 1,
            'parent_id' => null,
            'permission_id' => $permissions['manage-users']->id,
        ]);



        // CRM Settings
        $settings = $this->createRoute([
            'name' => 'Settings',
            'slug' => 'crm-settings',
            'path' => '/',
            'icon' => 'Cog',
            'order' => 6,
            'parent_id' => null,
            'permission_id' => $permissions['manage-settings']->id,
        ]);

        // Settings - Profile
        $this->createRoute([
            'name' => 'Profile',
            'slug' => 'crm-settings-profile',
            'path' => '/crm/settings/profile',
            'icon' => 'User',
            'order' => 1,
            'parent_id' => $settings->id,
            'permission_id' => $permissions['view-profile']->id,
        ]);

        // Settings - Appearance
        $this->createRoute([
            'name' => 'Appearance',
            'slug' => 'crm-settings-appearance',
            'path' => '/crm/settings/appearance',
            'icon' => 'Palette',
            'order' => 2,
            'parent_id' => $settings->id,
            'permission_id' => $permissions['manage-appearance']->id,
        ]);

        // Settings - Advanced
        $advanced = $this->createRoute([
            'name' => 'Advanced',
            'slug' => 'crm-settings-advanced',
            'path' => '/',
            'icon' => 'Cog6Tooth',
            'order' => 7,
            'parent_id' => null,
            'permission_id' => $permissions['manage-advanced-settings']->id,
        ]);

        $this->createRoute([
            'name' => 'Advanced Settings',
            'slug' => 'crm-settings-advance',
            'path' => '/crm/settings/advance',
            'icon' => 'AdjustmentsHorizontal',
            'order' => 1,
            'parent_id' => $advanced->id,
            'permission_id' => $permissions['manage-advanced-settings']->id,
        ]);

        $this->createRoute([
            'name' => 'Route Management',
            'slug' => 'crm-settings-advance-routes',
            'path' => '/crm/settings/advance/routes',
            'icon' => 'Map',
            'order' => 2,
            'parent_id' => $advanced->id,
            'permission_id' => $permissions['manage-routes-settings']->id,
        ]);

        // Settings - Permissions
        $permissions_section = $this->createRoute([
            'name' => 'Permissions',
            'slug' => 'crm-settings-permissions',
            'path' => '/',
            'icon' => 'ShieldCheck',
            'order' => 4,
            'parent_id' => null,
            'permission_id' => $permissions['manage-permissions']->id,
        ]);

        $this->createRoute([
            'name' => 'Permissions Overview',
            'slug' => 'crm-settings-permissions-overview',
            'path' => '/crm/settings/permissions',
            'icon' => 'Eye',
            'order' => 1,
            'parent_id' => $permissions_section->id,
            'permission_id' => $permissions['manage-permissions']->id,
        ]);

        $this->createRoute([
            'name' => 'General Permissions',
            'slug' => 'crm-settings-permissions-general',
            'path' => '/crm/settings/permissions/permissions',
            'icon' => 'Key',
            'order' => 2,
            'parent_id' => $permissions_section->id,
            'permission_id' => $permissions['manage-permissions']->id,
        ]);

        $this->createRoute([
            'name' => 'Roles',
            'slug' => 'crm-settings-permissions-roles',
            'path' => '/crm/settings/permissions/roles',
            'icon' => 'UserGroup',
            'order' => 3,
            'parent_id' => $permissions_section->id,
            'permission_id' => $permissions['manage-roles']->id,
        ]);

        $this->createRoute([
            'name' => 'Route Permissions',
            'slug' => 'crm-settings-permissions-routes',
            'path' => '/crm/settings/permissions/route-permissions',
            'icon' => 'Map',
            'order' => 4,
            'parent_id' => $permissions_section->id,
            'permission_id' => $permissions['manage-route-permissions']->id,
        ]);

        $this->createRoute([
            'name' => 'User Permissions',
            'slug' => 'crm-settings-permissions-users',
            'path' => '/crm/settings/permissions/user-permissions',
            'icon' => 'Users',
            'order' => 5,
            'parent_id' => $permissions_section->id,
            'permission_id' => $permissions['manage-user-permissions']->id,
        ]);

        // Support
        $support = $this->createRoute([
            'name' => 'Support',
            'slug' => 'crm-support',
            'path' => '/crm/support',
            'icon' => 'LifeBuoy',
            'order' => 7,
            'parent_id' => null,
            'permission_id' => $permissions['view-support']->id,
        ]);

        // Feedback
        $this->createRoute([
            'name' => 'Feedback',
            'slug' => 'crm-feedback',
            'path' => '/crm/feedback',
            'icon' => 'MessageSquare',
            'order' => 8,
            'parent_id' => null,
            'permission_id' => $permissions['submit-feedback']->id,
        ]);
    }

    /**
     * Create a route with the given attributes
     *
     * @param array $attributes
     * @return \App\Models\Route
     */
    private function createRoute(array $attributes)
    {
        return Route::firstOrCreate(
            ['slug' => $attributes['slug']],
            array_merge([
                'created_by' => 1,
                'updated_by' => 1,
                'description' => $attributes['name'] . ' route',
                'status' => true,
            ], $attributes)
        );
    }
}
