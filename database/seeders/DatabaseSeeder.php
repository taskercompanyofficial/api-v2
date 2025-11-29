<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        User::factory()->create([
            'name' => 'Muhammad Hanzla',
            'email' => 'shanzla765@gmail.com',
            'password' => 'Ukhadija15@',
        ]);

        // Create default roles
        Role::factory()->count(5)->create();
        
        // Create default statuses
        Status::factory()->count(5)->create();
        
        // Create default permissions
        // Permission::factory()->count(10)->create();
        
        // Run the route seeder to create navigation structure
        // $this->call([
        //     RouteSeeder::class,
        // ]);
    }
}
