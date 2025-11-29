<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $products = [
            // Air Conditioners
            ['name' => 'Wall-mounted Split AC', 'description' => 'A residential split air conditioner mounted on the wall, providing efficient cooling for single rooms.'],
            ['name' => 'Floor-mounted Split AC', 'description' => 'Split AC unit installed near the floor, ideal for larger rooms with improved airflow circulation.'],
            ['name' => 'Ceiling Cassette AC', 'description' => 'A ceiling-mounted cassette AC that distributes air evenly in four directions, commonly used in offices and commercial spaces.'],
            ['name' => 'Window AC', 'description' => 'A compact air conditioner that fits into a window, suitable for cooling single rooms.'],
            ['name' => 'Portable AC', 'description' => 'A movable air conditioning unit that can be relocated to different rooms as needed.'],
            ['name' => 'VRF / VRV System', 'description' => 'Variable Refrigerant Flow or Volume system for commercial buildings, allowing individual zone control and high efficiency.'],
            ['name' => 'Ducted / Central AC', 'description' => 'A central air conditioning system that cools multiple rooms through ductwork and vents.'],
            ['name' => 'Rooftop Unit (RTU)', 'description' => 'A self-contained commercial HVAC unit installed on rooftops, providing cooling and heating for large buildings.'],
            ['name' => 'Chiller (Air-cooled)', 'description' => 'A cooling system using air to remove heat, typically used in commercial or industrial applications.'],
            ['name' => 'Chiller (Water-cooled)', 'description' => 'A water-cooled chiller that uses water to remove heat, ideal for large-scale HVAC systems.'],

            // Microwaves
            ['name' => 'Solo Microwave', 'description' => 'A basic microwave oven for simple heating and cooking, without grill or convection features.'],
            ['name' => 'Grill Microwave', 'description' => 'Microwave with grilling feature to brown and crisp food along with regular microwave heating.'],
            ['name' => 'Convection Microwave', 'description' => 'Microwave with convection mode, suitable for baking, roasting, and grilling.'],

            // Refrigerators
            ['name' => 'Single Door Refrigerator', 'description' => 'Compact refrigerator with a single door, suitable for small households.'],
            ['name' => 'Double Door Refrigerator', 'description' => 'Refrigerator with separate freezer and fridge compartments, common in homes.'],
            ['name' => 'Side-by-Side Refrigerator', 'description' => 'Large refrigerator with side-by-side freezer and fridge compartments for easy access and storage.'],
            ['name' => 'Mini / Compact Refrigerator', 'description' => 'Small-sized fridge for dorms, offices, or personal use.'],
            ['name' => 'Commercial Refrigerator', 'description' => 'Heavy-duty refrigerator designed for commercial kitchens, restaurants, and stores.'],

            // LED / TVs
            ['name' => 'LED TV', 'description' => 'Standard LED television providing bright and energy-efficient display.'],
            ['name' => 'Smart LED TV', 'description' => 'LED TV with built-in smart features, allowing apps, streaming, and internet connectivity.'],
            ['name' => 'OLED TV', 'description' => 'High-end TV with OLED display for superior color, contrast, and viewing angles.'],
            ['name' => 'QLED TV', 'description' => 'LED TV with quantum-dot technology, providing enhanced brightness and color.'],

            // Washing Machines
            ['name' => 'Top-Load Washing Machine', 'description' => 'Washing machine with top-loading door, easy to use, common in homes.'],
            ['name' => 'Front-Load Washing Machine', 'description' => 'Efficient washing machine with front-loading door, uses less water and energy.'],
            ['name' => 'Semi-Automatic Washing Machine', 'description' => 'Washing machine requiring manual intervention for water filling and drainage.'],
            ['name' => 'Fully Automatic Washing Machine', 'description' => 'Complete washing machine with automatic wash, rinse, and spin cycles.'],
        ];

        foreach ($products as $product) {
            Product::create([
                'name' => $product['name'],
                'slug' => Str::slug($product['name']),
                'description' => $product['description'],
                'images' => [],
                'tags' => null,
                'status' => 'active',
                'notes' => null,
                'created_by' => 1,
                'updated_by' => 1,
            ]);
        }
    }
}
