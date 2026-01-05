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
        // Remove all existing parts before seeding
        Part::truncate();
        
        // Define specific parts for different product types
        $partsMap = [
            'Air Conditioner' => [
                // Indoor Parts
                ['part_number' => 'AC-IND-001', 'name' => 'Coil Sensor (IND)', 'category' => 'Electronics', 'description' => 'Indoor unit coil temperature sensor for air conditioner'],
                ['part_number' => 'AC-IND-002', 'name' => 'Temp Sensor (IND)', 'category' => 'Electronics', 'description' => 'Indoor unit room temperature sensor for air conditioner'],
                ['part_number' => 'AC-IND-003', 'name' => 'Display (IND)', 'category' => 'Electronics', 'description' => 'Indoor unit display panel for air conditioner'],
                ['part_number' => 'AC-IND-004', 'name' => 'PCB Kit (IND)', 'category' => 'Electronics', 'description' => 'Indoor unit printed circuit board kit for air conditioner'],
                ['part_number' => 'AC-IND-005', 'name' => 'Fan Motor (IND)', 'category' => 'Electrical', 'description' => 'Indoor unit fan motor for air conditioner'],
                ['part_number' => 'AC-IND-006', 'name' => 'Fan (IND)', 'category' => 'Mechanical', 'description' => 'Indoor unit fan blade for air conditioner'],
                ['part_number' => 'AC-IND-007', 'name' => 'Swing Motor U/D (IND)', 'category' => 'Electrical', 'description' => 'Indoor unit up/down swing motor for air conditioner'],
                ['part_number' => 'AC-IND-008', 'name' => 'Swing Motor R/L (IND)', 'category' => 'Electrical', 'description' => 'Indoor unit right/left swing motor for air conditioner'],
                ['part_number' => 'AC-IND-009', 'name' => 'Flaper (IND)', 'category' => 'Mechanical', 'description' => 'Indoor unit air flapper for air conditioner'],
                ['part_number' => 'AC-IND-010', 'name' => 'Evaporator (IND)', 'category' => 'Mechanical/Coil', 'description' => 'Indoor unit evaporator coil for air conditioner'],
                ['part_number' => 'AC-IND-011', 'name' => 'Base (IND)', 'category' => 'Mechanical', 'description' => 'Indoor unit base/chassis for air conditioner'],
                ['part_number' => 'AC-IND-012', 'name' => 'Gril Cover (IND)', 'category' => 'Mechanical', 'description' => 'Indoor unit grill cover for air conditioner'],
                ['part_number' => 'AC-IND-013', 'name' => 'Top Cover (IND)', 'category' => 'Mechanical', 'description' => 'Indoor unit top cover for air conditioner'],
                ['part_number' => 'AC-IND-014', 'name' => 'Remote (IND)', 'category' => 'Accessory', 'description' => 'Indoor unit remote controller for air conditioner'],
                ['part_number' => 'AC-IND-015', 'name' => 'Sport Plate (IND)', 'category' => 'Mechanical', 'description' => 'Indoor unit support plate for air conditioner'],
                ['part_number' => 'AC-IND-016', 'name' => 'AC Protect Cover (IND)', 'category' => 'Accessory', 'description' => 'Indoor unit protective cover for air conditioner'],
                ['part_number' => 'AC-IND-017', 'name' => 'Control Wire (IND)', 'category' => 'Electrical', 'description' => 'Indoor unit control wire for air conditioner'],
                ['part_number' => 'AC-IND-018', 'name' => 'Power Bracker (IND)', 'category' => 'Electrical', 'description' => 'Indoor unit power bracket for air conditioner'],
                ['part_number' => 'AC-IND-019', 'name' => 'Main Wire (IND)', 'category' => 'Electrical', 'description' => 'Indoor unit main power wire for air conditioner'],
                ['part_number' => 'AC-IND-020', 'name' => 'Drain Pipe (IND)', 'category' => 'Mechanical', 'description' => 'Indoor unit drain pipe for air conditioner'],
                ['part_number' => 'AC-IND-021', 'name' => 'Drain Pump (IND)', 'category' => 'Electrical', 'description' => 'Indoor unit drain pump for air conditioner'],
                ['part_number' => 'AC-IND-022', 'name' => 'PCB Kit Cover (IND)', 'category' => 'Mechanical', 'description' => 'Indoor unit PCB kit cover for air conditioner'],
                ['part_number' => 'AC-IND-023', 'name' => 'Drain Lavel Switch (IND)', 'category' => 'Electrical', 'description' => 'Indoor unit drain level switch for air conditioner'],
                ['part_number' => 'AC-IND-024', 'name' => 'Air Filter (IND)', 'category' => 'Filter', 'description' => 'Indoor unit air filter for air conditioner'],
                ['part_number' => 'AC-IND-025', 'name' => 'Wifi Device (IND)', 'category' => 'Electronics', 'description' => 'Indoor unit WiFi module for air conditioner'],
                ['part_number' => 'AC-IND-026', 'name' => 'Power Conector (IND)', 'category' => 'Electrical', 'description' => 'Indoor unit power connector for air conditioner'],
                
                // Outdoor Parts
                ['part_number' => 'AC-OT-001', 'name' => 'Coil Sensor (OD)', 'category' => 'Electronics', 'description' => 'Outdoor unit coil temperature sensor for air conditioner'],
                ['part_number' => 'AC-OT-002', 'name' => 'Temp Sensor (OD)', 'category' => 'Electronics', 'description' => 'Outdoor unit ambient temperature sensor for air conditioner'],
                ['part_number' => 'AC-OT-003', 'name' => 'Power Conector (OD)', 'category' => 'Electrical', 'description' => 'Outdoor unit power connector for air conditioner'],
                ['part_number' => 'AC-OT-004', 'name' => 'PCB Kit (OD)', 'category' => 'Electronics', 'description' => 'Outdoor unit printed circuit board kit for air conditioner'],
                ['part_number' => 'AC-OT-005', 'name' => 'Fan Motor (OD)', 'category' => 'Electrical', 'description' => 'Outdoor unit fan motor for air conditioner'],
                ['part_number' => 'AC-OT-006', 'name' => 'Fan Blade (OD)', 'category' => 'Mechanical', 'description' => 'Outdoor unit fan blade for air conditioner'],
                ['part_number' => 'AC-OT-007', 'name' => 'Runing Capacitor (OD)', 'category' => 'Electrical', 'description' => 'Outdoor unit running capacitor for air conditioner'],
                ['part_number' => 'AC-OT-008', 'name' => 'Fan Capacitor (OD)', 'category' => 'Electrical', 'description' => 'Outdoor unit fan capacitor for air conditioner'],
                ['part_number' => 'AC-OT-009', 'name' => 'Magnatic Conector (OD)', 'category' => 'Electrical', 'description' => 'Outdoor unit magnetic connector for air conditioner'],
                ['part_number' => 'AC-OT-010', 'name' => 'Compressor (OD)', 'category' => 'Mechanical', 'description' => 'Outdoor unit compressor for air conditioner'],
                ['part_number' => 'AC-OT-011', 'name' => '4 Way Walve (OD)', 'category' => 'Mechanical/Valve', 'description' => 'Outdoor unit 4-way reversing valve for air conditioner'],
                ['part_number' => 'AC-OT-012', 'name' => '4 Way Walve Asambly (OD)', 'category' => 'Mechanical/Valve', 'description' => 'Outdoor unit 4-way valve assembly for air conditioner'],
                ['part_number' => 'AC-OT-013', 'name' => 'Service Walve 1/2 (OD)', 'category' => 'Mechanical/Valve', 'description' => 'Outdoor unit service valve 1/2 inch for air conditioner'],
                ['part_number' => 'AC-OT-014', 'name' => 'Service Walve 1/4 (OD)', 'category' => 'Mechanical/Valve', 'description' => 'Outdoor unit service valve 1/4 inch for air conditioner'],
                ['part_number' => 'AC-OT-015', 'name' => 'Service Walve 5/8 (OD)', 'category' => 'Mechanical/Valve', 'description' => 'Outdoor unit service valve 5/8 inch for air conditioner'],
                ['part_number' => 'AC-OT-016', 'name' => 'Service Walve 3/8 (OD)', 'category' => 'Mechanical/Valve', 'description' => 'Outdoor unit service valve 3/8 inch for air conditioner'],
                ['part_number' => 'AC-OT-017', 'name' => 'Service Walve 6/4 (OD)', 'category' => 'Mechanical/Valve', 'description' => 'Outdoor unit service valve 6/4 inch for air conditioner'],
                ['part_number' => 'AC-OT-018', 'name' => 'Drain Nusal (OD)', 'category' => 'Mechanical', 'description' => 'Outdoor unit drain nozzle for air conditioner'],
                ['part_number' => 'AC-OT-019', 'name' => 'HP Walve (OD)', 'category' => 'Mechanical/Valve', 'description' => 'Outdoor unit high pressure valve for air conditioner'],
                ['part_number' => 'AC-OT-020', 'name' => 'Compressor Ruber (OD)', 'category' => 'Mechanical', 'description' => 'Outdoor unit compressor rubber mount for air conditioner'],
                ['part_number' => 'AC-OT-021', 'name' => 'Capalry (OD)', 'category' => 'Mechanical', 'description' => 'Outdoor unit capillary tube for air conditioner'],
                ['part_number' => 'AC-OT-022', 'name' => 'Transformer (OD)', 'category' => 'Electrical', 'description' => 'Outdoor unit transformer for air conditioner'],
                ['part_number' => 'AC-OT-023', 'name' => 'PCB Kit Cover (OD)', 'category' => 'Mechanical', 'description' => 'Outdoor unit PCB kit cover for air conditioner'],
                ['part_number' => 'AC-OT-024', 'name' => '4 Way Walve Coil (OD)', 'category' => 'Electrical/Coil', 'description' => 'Outdoor unit 4-way valve coil for air conditioner'],
                ['part_number' => 'AC-OT-025', 'name' => 'Pipe Cover (OD)', 'category' => 'Mechanical', 'description' => 'Outdoor unit pipe cover for air conditioner'],
                ['part_number' => 'AC-OT-026', 'name' => 'Cundunser Coil (OD)', 'category' => 'Mechanical/Coil', 'description' => 'Outdoor unit condenser coil for air conditioner'],
            ],
            'Washing Machine' => [
                ['part_number' => 'WM-001', 'name' => 'PCB Kit (WM)', 'category' => 'Electronics', 'description' => 'Washing machine PCB kit'],
                ['part_number' => 'WM-002', 'name' => 'Universal PCB Kit (WM)', 'category' => 'Electronics', 'description' => 'Washing machine universal PCB kit'],
                ['part_number' => 'WM-003', 'name' => 'Display (WM)', 'category' => 'Electronics', 'description' => 'Washing machine display panel'],
                ['part_number' => 'WM-004', 'name' => 'Water Level Sensor (WM)', 'category' => 'Electronics', 'description' => 'Washing machine water level sensor'],
                ['part_number' => 'WM-005', 'name' => 'Water Level Pipe (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine water level pipe'],
                ['part_number' => 'WM-006', 'name' => 'Door Switch (WM)', 'category' => 'Electrical', 'description' => 'Washing machine door switch'],
                ['part_number' => 'WM-007', 'name' => 'Shakh Set (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine shakh set'],
                ['part_number' => 'WM-008', 'name' => 'Gair Box (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine gear box'],
                ['part_number' => 'WM-009', 'name' => 'Drain Motor (WM)', 'category' => 'Electrical', 'description' => 'Washing machine drain motor'],
                ['part_number' => 'WM-010', 'name' => 'Drain Pump (WM)', 'category' => 'Electrical', 'description' => 'Washing machine drain pump'],
                ['part_number' => 'WM-011', 'name' => 'Drain Asambly (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine drain assembly'],
                ['part_number' => 'WM-012', 'name' => 'Wash Motor (WM)', 'category' => 'Electrical', 'description' => 'Washing machine wash motor'],
                ['part_number' => 'WM-013', 'name' => 'Inlet Walve (WM)', 'category' => 'Mechanical/Valve', 'description' => 'Washing machine inlet valve'],
                ['part_number' => 'WM-014', 'name' => 'Drain Pipe (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine drain pipe'],
                ['part_number' => 'WM-015', 'name' => 'Main Water Pipe (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine main water pipe'],
                ['part_number' => 'WM-016', 'name' => 'Power Cable (WM)', 'category' => 'Electrical', 'description' => 'Washing machine power cable'],
                ['part_number' => 'WM-017', 'name' => 'Palceter (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine pulsator'],
                ['part_number' => 'WM-018', 'name' => '2 Way Switch (WM)', 'category' => 'Electrical', 'description' => 'Washing machine 2 way switch'],
                ['part_number' => 'WM-019', 'name' => 'Timer (WM)', 'category' => 'Electrical', 'description' => 'Washing machine timer'],
                ['part_number' => 'WM-020', 'name' => 'Puly (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine pulley'],
                ['part_number' => 'WM-021', 'name' => 'Belt (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine belt'],
                ['part_number' => 'WM-022', 'name' => 'Break Asambly (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine break assembly'],
                ['part_number' => 'WM-023', 'name' => 'Door (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine door'],
                ['part_number' => 'WM-024', 'name' => 'Drain Ceel (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine drain seal'],
                ['part_number' => 'WM-025', 'name' => 'Spin Below (WM)', 'category' => 'Mechanical', 'description' => 'Washing machine spin below'],
                ['part_number' => 'WM-026', 'name' => 'Spin Motor (WM)', 'category' => 'Electrical', 'description' => 'Washing machine spin motor'],
                ['part_number' => 'WM-027', 'name' => 'Capacitor (WM)', 'category' => 'Electrical', 'description' => 'Washing machine capacitor'],
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
                ['part_number' => 'MWO-001', 'name' => 'Heat Gun (MWO)', 'category' => 'Electronics', 'description' => 'Microwave oven heat gun/magnetron'],
                ['part_number' => 'MWO-002', 'name' => 'Daivod (MWO)', 'category' => 'Electronics', 'description' => 'Microwave oven diode'],
                ['part_number' => 'MWO-003', 'name' => 'Plate (MWO)', 'category' => 'Mechanical', 'description' => 'Microwave oven turntable plate'],
                ['part_number' => 'MWO-004', 'name' => 'Tray Motor (MWO)', 'category' => 'Electrical', 'description' => 'Microwave oven tray motor'],
                ['part_number' => 'MWO-005', 'name' => 'Abrak Sheet (MWO)', 'category' => 'Mechanical', 'description' => 'Microwave oven mica sheet'],
                ['part_number' => 'MWO-006', 'name' => 'Capacitor (MWO)', 'category' => 'Electrical', 'description' => 'Microwave oven high voltage capacitor'],
                ['part_number' => 'MWO-007', 'name' => 'Controle Panel (MWO)', 'category' => 'Electronics', 'description' => 'Microwave oven control panel'],
                ['part_number' => 'MWO-008', 'name' => 'Power Cable (MWO)', 'category' => 'Electrical', 'description' => 'Microwave oven power cable'],
                ['part_number' => 'MWO-009', 'name' => 'Door Switch (MWO)', 'category' => 'Electrical', 'description' => 'Microwave oven door switch'],
                ['part_number' => 'MWO-010', 'name' => 'Door ASAMBLY (MWO)', 'category' => 'Mechanical', 'description' => 'Microwave oven door assembly'],
                ['part_number' => 'MWO-011', 'name' => 'Transformer (MWO)', 'category' => 'Electrical', 'description' => 'Microwave oven high voltage transformer'],
                ['part_number' => 'MWO-012', 'name' => 'Fuse (MWO)', 'category' => 'Electrical', 'description' => 'Microwave oven fuse'],
                ['part_number' => 'MWO-013', 'name' => 'Bulbe (MWO)', 'category' => 'Electrical', 'description' => 'Microwave oven bulb'],
                ['part_number' => 'MWO-014', 'name' => 'Bulb Holder (MWO)', 'category' => 'Electrical', 'description' => 'Microwave oven bulb holder'],
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
                ['part_number' => 'GEYSER-001', 'name' => 'Controle Kit (Geyser)', 'category' => 'Electronics', 'description' => 'Geyser control kit'],
                ['part_number' => 'GEYSER-002', 'name' => 'Sensor (Geyser)', 'category' => 'Electronics', 'description' => 'Geyser temperature sensor'],
                ['part_number' => 'GEYSER-003', 'name' => 'Thermostate (Geyser)', 'category' => 'Electrical', 'description' => 'Geyser thermostat'],
                ['part_number' => 'GEYSER-004', 'name' => 'Heating Rod (Geyser)', 'category' => 'Electrical/Coil', 'description' => 'Geyser heating rod/element'],
                ['part_number' => 'GEYSER-005', 'name' => 'Power Cable (Geyser)', 'category' => 'Electrical', 'description' => 'Geyser power cable'],
                ['part_number' => 'GEYSER-006', 'name' => 'Power Bracker (Geyser)', 'category' => 'Electrical', 'description' => 'Geyser power bracket'],
                ['part_number' => 'GEYSER-007', 'name' => 'No Return Walve (Geyser)', 'category' => 'Mechanical/Valve', 'description' => 'Geyser no return valve'],
                ['part_number' => 'GEYSER-008', 'name' => 'Sparker (Geyser)', 'category' => 'Electrical', 'description' => 'Geyser sparker/igniter'],
                ['part_number' => 'GEYSER-009', 'name' => 'Seel (Geyser)', 'category' => 'Mechanical', 'description' => 'Geyser seal/gasket'],
                ['part_number' => 'GEYSER-010', 'name' => 'Conection Pipe (Geyser)', 'category' => 'Mechanical', 'description' => 'Geyser connection pipe'],
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
                    'part_number' => $pData['part_number'] ?? 'PART-' . strtoupper(Str::random(8)),
                    'name' => $pData['name'],
                    'slug' => Str::slug($pData['name'] . '-' . $product->id),
                    'product_id' => $product->id,
                    'description' => $pData['description'] ?? "Genuine {$pData['name']} for product: {$productName}",
                    'status' => 'active',
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);
            }
        }

        $this->command->info('Parts (including Valves and Coils) seeded successfully!');
    }
}
