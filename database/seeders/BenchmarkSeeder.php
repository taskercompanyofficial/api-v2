<?php

namespace Database\Seeders;

use App\Models\Benchmark;
use Illuminate\Database\Seeder;

class BenchmarkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $benchmarks = [
            // KPI Resolution Targets
            [
                'category' => 'kpi_resolution',
                'key' => 'same_day',
                'label' => 'Same Day',
                'target_value' => 85.00,
                'min_value' => 0,
                'max_value' => 0,
                'order_index' => 1,
            ],
            [
                'category' => 'kpi_resolution',
                'key' => '1_day',
                'label' => '1 Day',
                'target_value' => 80.00,
                'min_value' => 1,
                'max_value' => 1,
                'order_index' => 2,
            ],
            [
                'category' => 'kpi_resolution',
                'key' => '2_day',
                'label' => '2 Day',
                'target_value' => 75.00,
                'min_value' => 2,
                'max_value' => 2,
                'order_index' => 3,
            ],
            [
                'category' => 'kpi_resolution',
                'key' => '3_4_day',
                'label' => '3-4 Days',
                'target_value' => 70.00,
                'min_value' => 3,
                'max_value' => 4,
                'order_index' => 4,
            ],
            [
                'category' => 'kpi_resolution',
                'key' => '5_6_day',
                'label' => '5-6 Days',
                'target_value' => 60.00,
                'min_value' => 5,
                'max_value' => 6,
                'order_index' => 5,
            ],
            [
                'category' => 'kpi_resolution',
                'key' => '7_8_day',
                'label' => '7-8 Days',
                'target_value' => 50.00,
                'min_value' => 7,
                'max_value' => 8,
                'order_index' => 6,
            ],
            [
                'category' => 'kpi_resolution',
                'key' => '8_plus_day',
                'label' => '8+ Days',
                'target_value' => 40.00,
                'min_value' => 9,
                'max_value' => 999,
                'order_index' => 7,
            ],

            // NPS Configurations
            [
                'category' => 'nps',
                'key' => 'nps_detractor',
                'label' => 'Detractors',
                'target_value' => 0, // Not used for bucket
                'min_value' => 1,
                'max_value' => 6,
                'order_index' => 1,
            ],
            [
                'category' => 'nps',
                'key' => 'nps_passive',
                'label' => 'Passives',
                'target_value' => 0,
                'min_value' => 7,
                'max_value' => 8,
                'order_index' => 2,
            ],
            [
                'category' => 'nps',
                'key' => 'nps_promoter',
                'label' => 'Promoters',
                'target_value' => 0,
                'min_value' => 9,
                'max_value' => 10,
                'order_index' => 3,
            ],
            [
                'category' => 'nps_targets',
                'key' => 'excellent',
                'label' => 'Excellent',
                'target_value' => 70.00,
                'min_value' => 70,
                'max_value' => 100,
                'order_index' => 1,
            ],
            [
                'category' => 'nps_targets',
                'key' => 'good',
                'label' => 'Good',
                'target_value' => 50.00,
                'min_value' => 50,
                'max_value' => 69,
                'order_index' => 2,
            ],
            [
                'category' => 'nps_targets',
                'key' => 'average',
                'label' => 'Average',
                'target_value' => 0.00,
                'min_value' => 0,
                'max_value' => 49,
                'order_index' => 3,
            ],
        ];

        foreach ($benchmarks as $data) {
            Benchmark::updateOrCreate(
                ['category' => $data['category'], 'key' => $data['key']],
                $data
            );
        }
    }
}
