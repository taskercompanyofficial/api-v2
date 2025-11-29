<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Route;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Route>
 */
class RouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        return [
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'path' => '/' . Str::slug($name),
            'icon' => $this->getRandomIcon(),
            'order' => $this->faker->numberBetween(1, 100),
            'parent_id' => null,
            'permission_id' => null,
            'status' => true,
        ];
    }

    /**
     * Configure the route as a parent route (menu section)
     *
     * @return $this
     */
    public function parent()
    {
        return $this->state(function (array $attributes) {
            return [
                'path' => '/', // Parent routes typically don't have paths
                'order' => $this->faker->numberBetween(1, 10),
                'icon' => $this->getRandomIcon(),
            ];
        });
    }

    /**
     * Configure the route as a child route
     *
     * @param int|null $parentId
     * @return $this
     */
    public function child($parentId = null)
    {
        return $this->state(function (array $attributes) use ($parentId) {
            // If no parent ID is provided, try to find an existing parent
            if ($parentId === null) {
                $parent = Route::whereNull('parent_id')->inRandomOrder()->first();
                $parentId = $parent ? $parent->id : null;
            }
            
            return [
                'parent_id' => $parentId,
                'order' => $this->faker->numberBetween(1, 20),
            ];
        });
    }

    /**
     * Assign a permission to the route
     *
     * @param int|null $permissionId
     * @return $this
     */
    public function withPermission($permissionId = null)
    {
        return $this->state(function (array $attributes) use ($permissionId) {
            // If no permission ID is provided, try to find an existing permission or create one
            if ($permissionId === null) {
                $permission = Permission::inRandomOrder()->first();
                $permissionId = $permission ? $permission->id : Permission::factory()->create()->id;
            }
            
            return [
                'permission_id' => $permissionId,
            ];
        });
    }

    /**
     * Set the route as inactive
     *
     * @return $this
     */
    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => false,
            ];
        });
    }
    
    /**
     * Get a random icon name for the sidebar
     *
     * @return string
     */
    private function getRandomIcon(): string
    {
        $icons = [
            'home', 'dashboard', 'settings', 'users', 'chart-pie', 'chart-bar', 
            'document', 'folder', 'calendar', 'mail', 'chat', 'bell', 
            'shield-check', 'cog', 'user-group', 'credit-card', 'clipboard-list',
            'globe', 'cube', 'desktop-computer', 'server', 'database'
        ];
        
        return $icons[array_rand($icons)];
    }
}
