<?php

namespace App\Http\Controllers\Authenticated\StaffApp;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StaffProfileController extends Controller
{
    /**
     * Update staff profile with required information
     */
    public function completeProfile(Request $request)
    {
        $user = $request->user();
        $staff = Staff::find($user->id);

        if (!$staff) {
            return response()->json([
                'status' => 'error',
                'message' => 'Staff not found.',
            ], 404);
        }

        $validated = $request->validate([
            'cnic' => 'required|string|size:13|unique:staff,cnic,' . $staff->id,
            'dob' => 'required|date|before:' . now()->subYears(18)->format('Y-m-d'),
            'gender' => 'required|in:male,female,other',
            'permanent_address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'profile_image' => 'nullable|string', // Base64 or file path
            'cnic_front_image' => 'nullable|string',
            'cnic_back_image' => 'nullable|string',
        ]);

        // Update staff record
        $staff->update($validated);

        // Reload staff with designation
        $staff = Staff::with('designation:id,name')->find($staff->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile completed successfully.',
            'user' => $staff,
        ], 200);
    }

    /**
     * Handle image upload from base64 or file
     */
    private function handleImageUpload($imageData, $folder, $filename)
    {
        // Check if it's a base64 encoded image
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif

            $imageData = base64_decode($imageData);

            if ($imageData === false) {
                throw new \Exception('Base64 decode failed');
            }

            $filename = $filename . '_' . time() . '.' . $type;
            $path = $folder . '/' . $filename;

            Storage::disk('public')->put($path, $imageData);

            return '/storage/' . $path;
        }

        // If it's already a URL or path, return as is
        return $imageData;
    }

    /**
     * Get profile completion status
     */
    public function getProfileStatus(Request $request)
    {
        $user = $request->user();
        $staff = Staff::find($user->id);

        if (!$staff) {
            return response()->json([
                'status' => 'error',
                'message' => 'Staff not found.',
            ], 404);
        }

        $isComplete = $this->checkProfileCompletion($staff);

        return response()->json([
            'is_profile_complete' => $isComplete,
            'missing_fields' => $this->getMissingFields($staff),
        ], 200);
    }

    /**
     * Check if profile is complete
     */
    private function checkProfileCompletion($staff)
    {
        $requiredFields = [
            'cnic',
            'dob',
            'gender',
            'permanent_address',
            'city',
            'state',
            'postal_code',
            'profile_picture',
            'cnic_front_image',
            'cnic_back_image',
        ];

        foreach ($requiredFields as $field) {
            if (empty($staff->$field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get list of missing required fields
     */
    private function getMissingFields($staff)
    {
        $requiredFields = [
            'cnic' => 'CNIC Number',
            'dob' => 'Date of Birth',
            'gender' => 'Gender',
            'permanent_address' => 'Permanent Address',
            'city' => 'City',
            'state' => 'State/Province',
            'postal_code' => 'Postal Code',
            'profile_picture' => 'Profile Picture',
            'cnic_front_image' => 'CNIC Front Image',
            'cnic_back_image' => 'CNIC Back Image',
        ];

        $missing = [];

        foreach ($requiredFields as $field => $label) {
            if (empty($staff->$field)) {
                $missing[] = [
                    'field' => $field,
                    'label' => $label,
                ];
            }
        }

        return $missing;
    }
}
