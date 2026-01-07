<?php

namespace Database\Seeders;

use App\Models\{ParentServices, ServiceConcern, ServiceSubConcern};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceConcernSeeder extends Seeder
{
    public function run(): void
    {
        $parentServices = [
            'repair' => ParentServices::where('slug', 'like', '%repair%')->orWhere('name', 'like', '%repair%')->first(),
            'installation' => ParentServices::where('slug', 'like', '%install%')->orWhere('name', 'like', '%install%')->first(),
            'maintenance' => ParentServices::where('slug', 'like', '%maintain%')->orWhere('name', 'like', '%maintain%')->first(),
        ];

        // === REPAIR CONCERNS ===
        if ($repair = $parentServices['repair']) {
            // 1. Error Codes
            $errorCodes = ServiceConcern::create([
                'parent_service_id' => $repair->id,
                'name' => 'Error Codes',
                'slug' => 'error-codes',
                'description' => 'AC displaying error codes on screen',
                'display_order' => 1,
                'icon' => '⚠️'
            ]);

            $errorCodesList = [
                ['E1', 'Sensor Fault', 'Temperature sensor malfunction'],
                ['E2', 'Compressor Issue', 'Compressor not working properly'],
                ['E3', 'Communication Error', 'Indoor-outdoor communication failure'],
                ['H1', 'Defrost Error', 'Defrosting system malfunction'],
                ['F0', 'Overload Protection', 'Refrigerant overload detected'],
                ['E4', 'High Pressure Error', 'High pressure detected in system'],
                ['E5', 'Low Pressure Error', 'Low refrigerant pressure'],
            ];

            foreach ($errorCodesList as  $index => [$code, $name, $desc]) {
                ServiceSubConcern::create([
                    'service_concern_id' => $errorCodes->id,
                    'name' => $name,
                    'code' => $code,
                    'slug' => 'error-' . strtolower($code),
                    'description' => $desc,
                    'display_order' => $index + 1
                ]);
            }

            // 2. Performance Issues
            $performance = ServiceConcern::create([
                'parent_service_id' => $repair->id,
                'name' => 'Performance Issues',
                'slug' => 'performance-issues',
                'description' => 'AC not performing as expected',
                'display_order' => 2,
                'icon' => '📉'
            ]);

            $performanceIssues = [
                'Low Cooling',
                'Not Cooling',
                'Water Leakage',
                'Strange Noise',
                'Foul Smell',
                'High Power Consumption',
                'Remote Not Working',
                'Auto Restart Issues'
            ];

            foreach ($performanceIssues as  $index => $issue) {
                ServiceSubConcern::create([
                    'service_concern_id' => $performance->id,
                    'name' => $issue,
                    'slug' => Str::slug($issue),
                    'display_order' => $index + 1
                ]);
            }

            // 3. Physical Damage
            $physical = ServiceConcern::create([
                'parent_service_id' => $repair->id,
                'name' => 'Physical Damage',
                'slug' => 'physical-damage',
                'description' => 'Physical or structural issues',
                'display_order' => 3,
                'icon' => '🔨'
            ]);

            $physicalIssues = [
                'Broken Cover',
                'Damaged Blade',
                'Cracked Drain Pipe',
                'Loose Mounting',
                'Corrosion'
            ];

            foreach ($physicalIssues as $index => $issue) {
                ServiceSubConcern::create([
                    'service_concern_id' => $physical->id,
                    'name' => $issue,
                    'slug' => Str::slug($issue),
                    'display_order' => $index + 1
                ]);
            }
        }

        // === INSTALLATION CONCERNS ===
        if ($installation = $parentServices['installation']) {
            $installationType = ServiceConcern::create([
                'parent_service_id' => $installation->id,
                'name' => 'Installation Type',
                'slug' => 'installation-type',
                'description' => 'Type of installation required',
                'display_order' => 1,
                'icon' => '🔧'
            ]);

            $installationTypes = [
                ['Free Installation (Warranty)', 'New product installation covered under warranty'],
                ['Paid Installation', 'Standard paid installation service'],
                ['Replacement Installation', 'Replacing old unit with new one'],
                ['Relocation/Uninstall-Install', 'Moving AC from one location to another'],
            ];

            foreach ($installationTypes as $index => [$type, $desc]) {
                ServiceSubConcern::create([
                    'service_concern_id' => $installationType->id,
                    'name' => $type,
                    'slug' => Str::slug($type),
                    'description' => $desc,
                    'display_order' => $index + 1
                ]);
            }
        }

        // === MAINTENANCE CONCERNS ===
        if ($maintenance = $parentServices['maintenance']) {
            $maintenanceType = ServiceConcern::create([
                'parent_service_id' => $maintenance->id,
                'name' => 'Maintenance Type',
                'slug' => 'maintenance-type',
                'description' => 'Type of maintenance service',
                'display_order' => 1,
                'icon' => '🔍'
            ]);

            $maintenanceTypes = [
                'Routine Checkup',
                'Deep Cleaning',
                'Gas Refilling',
                'Filter Replacement',
                'Coil Cleaning',
                'Annual Service Package'
            ];

            foreach ($maintenanceTypes as $index => $type) {
                ServiceSubConcern::create([
                    'service_concern_id' => $maintenanceType->id,
                    'name' => $type,
                    'slug' => Str::slug($type),
                    'display_order' => $index + 1
                ]);
            }
        }
    }
}
