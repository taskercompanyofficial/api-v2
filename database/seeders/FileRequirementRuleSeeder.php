<?php

namespace Database\Seeders;

use App\Models\{ParentServices, ServiceConcern, ServiceSubConcern, FileType, FileRequirementRule};
use Illuminate\Database\Seeder;

class FileRequirementRuleSeeder extends Seeder
{
    public function run(): void
    {
        // Get parent services
        $repair = ParentServices::where('slug', 'like', '%repair%')->orWhere('name', 'like', '%repair%')->first();
        $installation = ParentServices::where('slug', 'like', '%install%')->orWhere('name', 'like', '%install%')->first();

        // Get file types (create if don't exist)
        $fileTypes = [
            'warranty-card' => FileType::firstOrCreate(['slug' => 'warranty-card'], [
                'name' => 'Warranty Card',
                'description' => 'Warranty card photo',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/png', 'application/pdf'],
                'extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
                'is_image' => true,
                'status' => true,
                'sort_order' => 1
            ]),
            'product-photo' => FileType::firstOrCreate(['slug' => 'product-photo'], [
                'name' => 'Product Photo',
                'description' => 'Photo of the product',
                'max_file_size' => 10485760, // 10MB
                'mime_types' => ['image/jpeg', 'image/png'],
                'extensions' => ['jpg', 'jpeg', 'png'],
                'is_image' => true,
                'status' => true,
                'sort_order' => 2
            ]),
            'payment-proof' => FileType::firstOrCreate(['slug' => 'payment-proof'], [
                'name' => 'Payment Proof',
                'description' => 'Payment receipt or screenshot',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/png', 'application/pdf'],
                'extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
                'is_image' => true,
                'status' => true,
                'sort_order' => 3
            ]),
            'purchase-invoice' => FileType::firstOrCreate(['slug' => 'purchase-invoice'], [
                'name' => 'Purchase Invoice',
                'description' => 'Original purchase invoice',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/png', 'application/pdf'],
                'extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
                'is_document' => true,
                'status' => true,
                'sort_order' => 4
            ]),
        ];

        if ($repair) {
            // === WARRANTY REPAIR RULES ===
            FileRequirementRule::create([
                'name' => 'Warranty Card - Required for Warranty Repair',
                'description' => 'Warranty card must be uploaded for warranty cases',
                'parent_service_id' => $repair->id,
                'is_warranty_case' => true,
                'file_type_id' => $fileTypes['warranty-card']->id,
                'requirement_type' => 'required',
                'help_text' => 'Please upload a clear photo of the warranty card showing serial number',
                'validation_rules' => json_encode([
                    'max_size_mb' => 5,
                    'allowed_types' => ['image/jpeg', 'image/png', 'application/pdf']
                ]),
                'display_order' => 1,
                'priority' => 100
            ]);

            FileRequirementRule::create([
                'name' => 'Product Photos - Warranty Repair',
                'parent_service_id' => $repair->id,
                'is_warranty_case' => true,
                'file_type_id' => $fileTypes['product-photo']->id,
                'requirement_type' => 'required',
                'help_text' => 'Upload 2-5 photos showing the defective product',
                'validation_rules' => json_encode([
                    'max_size_mb' => 10,
                    'min_count' => 2,
                    'max_count' => 5
                ]),
                'display_order' => 2,
                'priority' => 90
            ]);

            // === PAID REPAIR RULES ===
            FileRequirementRule::create([
                'name' => 'Hide Warranty Card - Paid Repair',
                'parent_service_id' => $repair->id,
                'is_warranty_case' => false,
                'file_type_id' => $fileTypes['warranty-card']->id,
                'requirement_type' => 'hidden',
                'priority' => 100
            ]);

            FileRequirementRule::create([
                'name' => 'Payment Proof - Paid Repair',
                'parent_service_id' => $repair->id,
                'is_warranty_case' => false,
                'file_type_id' => $fileTypes['payment-proof']->id,
                'requirement_type' => 'required',
                'help_text' => 'Upload payment receipt or bank transfer screenshot',
                'display_order' => 1,
                'priority' => 90
            ]);

            FileRequirementRule::create([
                'name' => 'Product Photos - Paid Repair (Optional)',
                'parent_service_id' => $repair->id,
                'is_warranty_case' => false,
                'file_type_id' => $fileTypes['product-photo']->id,
                'requirement_type' => 'optional',
                'help_text' => 'Optional: Upload photos of the product',
                'display_order' => 2,
                'priority' => 80
            ]);
        }

        if ($installation) {
            // === FREE INSTALLATION RULES ===
            $freeInstallSub = ServiceSubConcern::where('slug', 'like', '%free%')->first();

            if ($freeInstallSub) {
                FileRequirementRule::create([
                    'name' => 'Purchase Invoice - Free Installation',
                    'parent_service_id' => $installation->id,
                    'service_sub_concern_id' => $freeInstallSub->id,
                    'file_type_id' => $fileTypes['purchase-invoice']->id,
                    'requirement_type' => 'required',
                    'help_text' => 'Original purchase invoice required for free installation verification',
                    'validation_rules' => json_encode(['max_size_mb' => 5]),
                    'display_order' => 1,
                    'priority' => 200  // Very specific rule
                ]);
            }

            // === PAID INSTALLATION RULES ===
            FileRequirementRule::create([
                'name' => 'Payment Proof - Paid Installation',
                'parent_service_id' => $installation->id,
                'file_type_id' => $fileTypes['payment-proof']->id,
                'requirement_type' => 'required',
                'help_text' => 'Upload payment proof for installation service',
                'display_order' => 1,
                'priority' => 90
            ]);
        }
    }
}
