<?php

namespace Database\Seeders;

use App\Models\Part;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define specific parts for different product types
        $partsMap = [
            'Air Conditioner' => [
                ['name' => 'Compressor', 'category' => 'Mechanical'],
                ['name' => 'Indoor PCB (Main)', 'category' => 'Electronics'],
                ['name' => 'Outdoor PCB (Inverter)', 'category' => 'Electronics'],
                ['name' => 'Indoor Fan Motor', 'category' => 'Electrical'],
                ['name' => 'Outdoor Fan Motor', 'category' => 'Electrical'],
                ['name' => 'Evaporator Coil (Indoor)', 'category' => 'Mechanical/Coil'],
                ['name' => 'Condenser Coil (Outdoor)', 'category' => 'Mechanical/Coil'],
                ['name' => '4-Way Reversing Valve', 'category' => 'Mechanical/Valve'],
                ['name' => 'Electronic Expansion Valve (EEV)', 'category' => 'Mechanical/Valve'],
                ['name' => 'Service Valve (Liquid/Suction)', 'category' => 'Mechanical/Valve'],
                ['name' => 'Solenoid Valve Coil', 'category' => 'Electrical/Coil'],
                ['name' => 'Capacitor 45uf', 'category' => 'Electrical'],
                ['name' => 'Refrigerant Gas R410A (1kg)', 'category' => 'Gas'],
                ['name' => 'Swing Motor', 'category' => 'Electrical'],
                ['name' => 'Installation Kit (Pipe/Insulation)', 'category' => 'Kits'],
                ['name' => 'Service & Cleaning Kit', 'category' => 'Kits'],
                ['name' => 'Remote Controller', 'category' => 'Accessory'],
            ],
            'Washing Machine' => [
                ['name' => 'Wash Motor', 'category' => 'Electrical'],
                ['name' => 'Spin Motor', 'category' => 'Electrical'],
                ['name' => 'Drain Pump Assembly', 'category' => 'Mechanical'],
                ['name' => 'Single Inlet Water Valve', 'category' => 'Mechanical/Valve'],
                ['name' => 'Double Inlet Water Valve', 'category' => 'Mechanical/Valve'],
                ['name' => 'Valve Coil Assembly', 'category' => 'Electrical/Coil'],
                ['name' => 'Main PCB Board', 'category' => 'Electronics'],
                ['name' => 'Door Lock Switch', 'category' => 'Electrical'],
                ['name' => 'Drive Belt', 'category' => 'Mechanical'],
                ['name' => 'Pulsator Assembly', 'category' => 'Mechanical'],
                ['name' => 'Bearing & Seal Repair Kit', 'category' => 'Kits'],
                ['name' => 'Suspension Spring Kit', 'category' => 'Kits'],
            ],
            'Refrigerator' => [
                ['name' => 'Compressor', 'category' => 'Mechanical'],
                ['name' => 'Evaporator Coil', 'category' => 'Mechanical/Coil'],
                ['name' => 'Condenser Coil', 'category' => 'Mechanical/Coil'],
                ['name' => 'Dispenser Water Valve', 'category' => 'Mechanical/Valve'],
                ['name' => 'Thermostat', 'category' => 'Electrical'],
                ['name' => 'Relay & Overload Kit', 'category' => 'Electrical'],
                ['name' => 'Evaporator Fan Motor', 'category' => 'Electrical'],
                ['name' => 'Defrost Heater', 'category' => 'Electrical'],
                ['name' => 'Door Gasket (Magnetic)', 'category' => 'Mechanical'],
                ['name' => 'Gas Charging & Filter Drier Kit', 'category' => 'Kits'],
            ],
            'Microwave Oven' => [
                ['name' => 'Magnetron', 'category' => 'Electronics'],
                ['name' => 'High Voltage Transformer', 'category' => 'Electrical'],
                ['name' => 'High Voltage Capacitor', 'category' => 'Electrical'],
                ['name' => 'Door Interlock Switch Kit', 'category' => 'Electrical'],
                ['name' => 'Main Control PCB', 'category' => 'Electronics'],
                ['name' => 'Turntable Motor', 'category' => 'Electrical'],
                ['name' => 'Glass Turntable Plate', 'category' => 'Mechanical'],
            ],
            'Water Dispenser' => [
                ['name' => 'Cooling Compressor', 'category' => 'Mechanical'],
                ['name' => 'Heating Element', 'category' => 'Electrical'],
                ['name' => 'Stainless Steel Hot Tank', 'category' => 'Mechanical'],
                ['name' => 'Hot Water Valve/Tap', 'category' => 'Mechanical/Valve'],
                ['name' => 'Cold Water Valve/Tap', 'category' => 'Mechanical/Valve'],
                ['name' => 'Dual Thermostat Kit', 'category' => 'Electrical'],
                ['name' => 'Internal Filter Kit', 'category' => 'Filter'],
            ],
            'LED TV' => [
                ['name' => 'Main Logic Board', 'category' => 'Electronics'],
                ['name' => 'Power Supply Board', 'category' => 'Electronics'],
                ['name' => 'T-Con Board', 'category' => 'Electronics'],
                ['name' => 'LED Backlight Strip Kit', 'category' => 'Kits'],
                ['name' => 'Full Display Panel', 'category' => 'Mechanical'],
            ],
            'Spinner' => [
                ['name' => 'High Torque Spin Motor', 'category' => 'Electrical'],
                ['name' => 'Mechanical Timer Assembly', 'category' => 'Electrical'],
                ['name' => 'Brake Mechanism Kit', 'category' => 'Kits'],
                ['name' => 'Stainless Steel Spin Tub', 'category' => 'Mechanical'],
            ],
            'Geyser' => [
                ['name' => 'Immersion Heating Element', 'category' => 'Electrical/Coil'],
                ['name' => 'Heating Coil Assembly', 'category' => 'Mechanical/Coil'],
                ['name' => 'Auto-Cut Thermostat', 'category' => 'Electrical'],
                ['name' => 'Magnesium Anode Kit', 'category' => 'Kits'],
                ['name' => 'Pressure Release Safety Valve', 'category' => 'Mechanical/Valve'],
                ['name' => 'Non-Return Valve (NRV)', 'category' => 'Mechanical/Valve'],
            ],
            'Vacuum Cleaner' => [
                ['name' => 'High Vacuum Suction Motor', 'category' => 'Electrical'],
                ['name' => 'HEPA Filter Set', 'category' => 'Filter'],
                ['name' => 'Carbon Brush Set', 'category' => 'Electrical'],
                ['name' => 'Flex Hose & Tube Kit', 'category' => 'Kits'],
            ],
            'Dishwasher' => [
                ['name' => 'Main Circulation Pump', 'category' => 'Electrical'],
                ['name' => 'Inlet Water Solenoid Valve', 'category' => 'Mechanical/Valve'],
                ['name' => 'Drain Pump Solenoid', 'category' => 'Electrical/Coil'],
                ['name' => 'Water Heating element (Coil)', 'category' => 'Electrical/Coil'],
                ['name' => 'Digital Control Module', 'category' => 'Electronics'],
                ['name' => 'Spray Arm & Hub Kit', 'category' => 'Kits'],
            ],
        ];

        // Get all products from DB
        $products = Product::all();

        foreach ($products as $product) {
            $productName = $product->name;
            
            // Find matching parts for this product type
            $partsToCreate = $partsMap[$productName] ?? [];

            // If no specific match, try a partial match
            if (empty($partsToCreate)) {
                foreach ($partsMap as $key => $parts) {
                    if (stripos($productName, $key) !== false) {
                        $partsToCreate = $parts;
                        break;
                    }
                }
            }

            // Fallback for unknown products
            if (empty($partsToCreate)) {
                $partsToCreate = [
                    ['name' => 'Maintenance Kit', 'category' => 'Kits'],
                    ['name' => 'Standard Spare Part', 'category' => 'Mechanical'],
                    ['name' => 'Electronic Component', 'category' => 'Electronics'],
                ];
            }

            foreach ($partsToCreate as $pData) {
                Part::create([
                    'part_number' => 'PART-' . strtoupper(Str::random(8)),
                    'name' => $pData['name'],
                    'slug' => Str::slug($pData['name'] . '-' . $product->id),
                    'product_id' => $product->id,
                    'description' => "Genuine {$pData['name']} for product: {$productName}",
                    'status' => 'active',
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);
            }
        }

        $this->command->info('Parts (including Valves and Coils) seeded successfully!');
    }
}
