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

            // 2. Fault Issues
            $fault = ServiceConcern::create([
                'parent_service_id' => $repair->id,
                'name' => 'Fault',
                'slug' => 'fault',
                'description' => 'AC fault and problems',
                'display_order' => 2,
                'icon' => '⚠️'
            ]);

            $faultIssues = [
                ['Heating Problem', 'AC is producing hot air instead of cooling'],
                ['Cooling Problem', 'AC not cooling properly or insufficient cooling'],
                ['Voltage Problem', 'Voltage fluctuation or electrical supply issues'],
                ['Water Problem Ind', 'Water leakage from indoor unit'],
                ['Water Problem OD', 'Water leakage from outdoor unit'],
                ['Noise Problem', 'Unusual noise coming from AC unit'],
                ['Ampaire Problem', 'High ampere draw or electrical overload'],
                ['Air Problem', 'Weak or no airflow from AC'],
                ['Smell Issue', 'Bad odor or foul smell from AC'],
                ['Service Issue', 'General service related problem'],
                ['Display Issue', 'Display panel not working or showing errors'],
                ['Sparking Issue', 'Electrical sparking in AC unit'],
                ['Gas Leakage', 'Refrigerant gas leaking from system'],
                ['Vibration', 'Excessive vibration during operation'],
                ['Earth Problem', 'Earthing/grounding issue causing shock'],
                ['Wifi Problem', 'WiFi connectivity or smart features not working'],
                ['Remote Problem', 'Remote control not functioning properly'],
                ['Installation Problem', 'Issues related to AC installation'],
                ['Pipe Insulation Problem', 'Damaged or missing pipe insulation'],
                ['Not On', 'AC unit not turning on or starting'],
                ['Compressor Problem', 'Compressor malfunction or failure'],
                ['Swing Problem', 'Louver/swing mechanism not working']
            ];

            foreach ($faultIssues as $index => [$name, $desc]) {
                ServiceSubConcern::create([
                    'service_concern_id' => $fault->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => $desc,
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
