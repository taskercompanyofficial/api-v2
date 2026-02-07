<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Food Allowance',
                'slug' => 'food-allowance',
                'description' => 'Daily food allowance for staff members',
                'is_active' => true,
            ],
            [
                'name' => 'Transport Allowance',
                'slug' => 'transport-allowance',
                'description' => 'Daily transport/travel allowance',
                'is_active' => true,
            ],
            [
                'name' => 'Fuel Allowance',
                'slug' => 'fuel-allowance',
                'description' => 'Fuel allowance for staff with vehicles',
                'is_active' => true,
            ],
            [
                'name' => 'Mobile Allowance',
                'slug' => 'mobile-allowance',
                'description' => 'Monthly mobile/phone allowance',
                'is_active' => true,
            ],
            [
                'name' => 'Overtime Allowance',
                'slug' => 'overtime-allowance',
                'description' => 'Allowance for overtime work',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
