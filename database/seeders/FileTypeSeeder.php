<?php

namespace Database\Seeders;

use App\Models\FileType;
use Illuminate\Database\Seeder;

class FileTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fileTypes = [
            // Serial Number Images
            [
                'name' => 'Indoor Serial Number Image',
                'slug' => 'indoor-serial-number-image',
                'description' => 'Image of indoor unit serial number',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#3B82F6',
                'is_image' => true,
                'status' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Outdoor Serial Number Image',
                'slug' => 'outdoor-serial-number-image',
                'description' => 'Image of outdoor unit serial number',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#3B82F6',
                'is_image' => true,
                'status' => true,
                'sort_order' => 2,
            ],

            // Product Images
            [
                'name' => 'Model Image',
                'slug' => 'model-image',
                'description' => 'Image of product model',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#10B981',
                'is_image' => true,
                'status' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Indoor Unit Image',
                'slug' => 'indoor-unit-image',
                'description' => 'Image of indoor unit',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#10B981',
                'is_image' => true,
                'status' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Outdoor Unit Image',
                'slug' => 'outdoor-unit-image',
                'description' => 'Image of outdoor unit',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#10B981',
                'is_image' => true,
                'status' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Proof of Installation',
                'slug' => 'proof-of-installation',
                'description' => 'Image showing proof of installation',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#8B5CF6',
                'is_image' => true,
                'status' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Hole Picture',
                'slug' => 'hole-picture',
                'description' => 'Image of installation hole',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#8B5CF6',
                'is_image' => true,
                'status' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Wiring Joints Picture',
                'slug' => 'wiring-joints-picture',
                'description' => 'Image of wiring joints',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#8B5CF6',
                'is_image' => true,
                'status' => true,
                'sort_order' => 8,
            ],

            // Technician Photos
            [
                'name' => 'Technician with Customer',
                'slug' => 'technician-with-customer',
                'description' => 'Photo of technician with customer',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#F59E0B',
                'is_image' => true,
                'status' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Technician with Product Indoor',
                'slug' => 'technician-with-product-indoor',
                'description' => 'Photo of technician with indoor product',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#F59E0B',
                'is_image' => true,
                'status' => true,
                'sort_order' => 10,
            ],

            // Documents
            [
                'name' => 'Invoice',
                'slug' => 'invoice',
                'description' => 'Invoice document (PDF or image)',
                'max_file_size' => 10485760, // 10MB
                'mime_types' => ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileText',
                'color' => '#EF4444',
                'is_document' => true,
                'status' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'Warranty Card',
                'slug' => 'warranty-card',
                'description' => 'Warranty card document (PDF or image)',
                'max_file_size' => 10485760, // 10MB
                'mime_types' => ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileText',
                'color' => '#EF4444',
                'is_document' => true,
                'status' => true,
                'sort_order' => 12,
            ],

            // Defect Images
            [
                'name' => 'Defect Image',
                'slug' => 'defect-image',
                'description' => 'Image showing product defect',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#DC2626',
                'is_image' => true,
                'status' => true,
                'sort_order' => 13,
            ],
            [
                'name' => 'Defect Issue Image',
                'slug' => 'defect-issue-image',
                'description' => 'Image showing defect issue details',
                'max_file_size' => 5242880, // 5MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'icon' => 'FileImage',
                'color' => '#DC2626',
                'is_image' => true,
                'status' => true,
                'sort_order' => 14,
            ],

            // Other Attachments
            [
                'name' => 'Other Images',
                'slug' => 'other-images',
                'description' => 'Any additional images or documents',
                'max_file_size' => 10485760, // 10MB
                'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'],
                'icon' => 'FileImage',
                'color' => '#6B7280',
                'is_image' => true,
                'status' => true,
                'sort_order' => 15,
            ],
        ];

        foreach ($fileTypes as $fileType) {
            FileType::updateOrCreate(
                ['slug' => $fileType['slug']],
                $fileType
            );
        }
    }
}
