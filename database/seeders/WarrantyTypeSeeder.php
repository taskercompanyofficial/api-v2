<?php

namespace Database\Seeders;

use App\Models\WarrantyType;
use Illuminate\Database\Seeder;

class WarrantyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warrantyTypes = [
            [
                'name' => 'Warranty',
                'slug' => 'warranty',
                'description' => 'Product is under manufacturer warranty',
                'icon' => '✅',
                'color' => '#10b981', // green
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Paid Service',
                'slug' => 'paid-service',
                'description' => 'Customer pays for repair/service',
                'icon' => '💰',
                'color' => '#f59e0b', // amber
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Extended Warranty',
                'slug' => 'extended-warranty',
                'description' => 'Product under extended/purchased warranty',
                'icon' => '🛡️',
                'color' => '#3b82f6', // blue
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'AMC (Annual Maintenance Contract)',
                'slug' => 'amc',
                'description' => 'Service under annual maintenance contract',
                'icon' => '📋',
                'color' => '#8b5cf6', // purple
                'display_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Free Service',
                'slug' => 'free-service',
                'description' => 'Complimentary service (goodwill, promotion, etc.)',
                'icon' => '🎁',
                'color' => '#ec4899', // pink
                'display_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Insurance Claim',
                'slug' => 'insurance-claim',
                'description' => 'Repair covered by insurance',
                'icon' => '🏥',
                'color' => '#06b6d4', // cyan
                'display_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($warrantyTypes as $type) {
            WarrantyType::updateOrCreate(
                ['slug' => $type['slug']],
                $type
            );
        }

        $this->command->info('Warranty types seeded successfully!');
    }
}
