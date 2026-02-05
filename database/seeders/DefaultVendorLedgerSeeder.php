<?php

namespace Database\Seeders;

use App\Models\DefaultVendorLedger;
use App\Models\ParentServices;
use Illuminate\Database\Seeder;

class DefaultVendorLedgerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get parent services for linking
        $parentServices = ParentServices::all()->keyBy('name');

        // Services with vendor rates
        $services = [
            [
                'item_type' => 'service',
                'parent_service_id' => $parentServices->get('AC Installation')?->id,
                'service_name' => 'AC Installation',
                'vendor_rate' => 1600.00,
                'is_active' => true,
                'description' => 'Standard air conditioner installation service',
            ],
            [
                'item_type' => 'service',
                'parent_service_id' => $parentServices->get('AC Repair')?->id,
                'service_name' => 'AC Repair',
                'vendor_rate' => 800.00,
                'is_active' => true,
                'description' => 'Air conditioner repair service',
            ],
            [
                'item_type' => 'service',
                'parent_service_id' => $parentServices->get('Washing Machine Installation')?->id,
                'service_name' => 'Washing Machine Installation',
                'vendor_rate' => 1300.00,
                'is_active' => true,
                'description' => 'Washing machine installation service',
            ],
            [
                'item_type' => 'service',
                'parent_service_id' => $parentServices->get('Refrigerator Repair')?->id,
                'service_name' => 'Refrigerator Repair',
                'vendor_rate' => 1000.00,
                'is_active' => true,
                'description' => 'Refrigerator repair service',
            ],
            [
                'item_type' => 'service',
                'service_name' => 'General Service',
                'vendor_rate' => 500.00,
                'is_active' => true,
                'description' => 'General maintenance and service',
            ],
        ];

        // Parts with cost prices and revenue share
        $parts = [
            [
                'item_type' => 'part',
                'part_name' => 'Gas R410A',
                'part_code' => 'GAS-R410A',
                'unit' => 'kg',
                'cost_price' => 1300.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => 'Refrigerant gas R410A',
            ],
            [
                'item_type' => 'part',
                'part_name' => 'Gas R22',
                'part_code' => 'GAS-R22',
                'unit' => 'kg',
                'cost_price' => 1100.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => 'Refrigerant gas R22',
            ],
            [
                'item_type' => 'part',
                'part_name' => 'Copper Pipe',
                'part_code' => 'PIPE-001',
                'unit' => 'feet',
                'cost_price' => 90.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => 'Copper pipe for AC installation',
            ],
            [
                'item_type' => 'part',
                'part_name' => 'Drain Pipe',
                'part_code' => 'PIPE-002',
                'unit' => 'feet',
                'cost_price' => 50.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => 'PVC drain pipe',
            ],
            [
                'item_type' => 'part',
                'part_name' => 'Capacitor 40MFD',
                'part_code' => 'CAP-001',
                'unit' => 'piece',
                'cost_price' => 200.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => '40 MFD capacitor',
            ],
            [
                'item_type' => 'part',
                'part_name' => 'Compressor Oil',
                'part_code' => 'OIL-001',
                'unit' => 'liter',
                'cost_price' => 500.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => 'Compressor oil for lubrication',
            ],
            [
                'item_type' => 'part',
                'part_name' => 'PCB Board',
                'part_code' => 'PCB-001',
                'unit' => 'piece',
                'cost_price' => 1500.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => 'Circuit control board',
            ],
            [
                'item_type' => 'part',
                'part_name' => 'Fan Motor',
                'part_code' => 'MTR-001',
                'unit' => 'piece',
                'cost_price' => 2000.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => 'Indoor/outdoor fan motor',
            ],
            [
                'item_type' => 'part',
                'part_name' => 'Thermostat',
                'part_code' => 'THRM-001',
                'unit' => 'piece',
                'cost_price' => 800.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => 'Temperature control thermostat',
            ],
            [
                'item_type' => 'part',
                'part_name' => 'Remote Control',
                'part_code' => 'RMT-001',
                'unit' => 'piece',
                'cost_price' => 600.00,
                'revenue_share_percentage' => 50.00,
                'is_active' => true,
                'description' => 'AC remote control',
            ],
        ];

        // Insert services
        foreach ($services as $service) {
            // Skip if parent service not found
            if ($service['item_type'] === 'service' && !isset($service['parent_service_id'])) {
                continue;
            }

            DefaultVendorLedger::create($service);
        }

        // Insert parts
        foreach ($parts as $part) {
            DefaultVendorLedger::create($part);
        }

        $this->command->info('Default vendor ledger seeded successfully!');
        $this->command->info('Created ' . count($services) . ' services and ' . count($parts) . ' parts.');
    }
}
