<?php

namespace App\Http\Controllers\Auth\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthenticatedSessionController extends Controller
{
    /**
     * Helper to store uploaded files
     */
    private function storeFile($file, $folder, $prefix)
    {
        if (!$file) return null;
        $extension = $file->getClientOriginalExtension();
        $fileName = $prefix . '_' . Str::random(10) . '.' . $extension;
        return $file->storeAs($folder, $fileName, 'public');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'first_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|nullable|string|max:100',
            'email' => 'sometimes|nullable|email|max:255|unique:vendors,email,' . $user->id,
            'phone' => 'sometimes|required|regex:/^\+?[0-9]{10,15}$/|unique:vendors,phone,' . $user->id,
            'cnic' => 'sometimes|nullable|string|max:20',
            'dob' => 'sometimes|nullable|date',
            'experience' => 'sometimes|nullable|string|max:255',
            'handled_categories' => 'sometimes|nullable|array',
            
            'profile_image_file' => 'sometimes|nullable|image|max:2048',
            'cnic_front_file' => 'sometimes|nullable|image|max:2048',
            'cnic_back_file' => 'sometimes|nullable|image|max:2048',
        ]);

        // Handle File Uploads
        if ($request->hasFile('profile_image_file')) {
            if ($user->profile_image) Storage::disk('public')->delete($user->profile_image);
            $validated['profile_image'] = $this->storeFile($request->file('profile_image_file'), 'vendors/profiles', 'profile');
        }
        if ($request->hasFile('cnic_front_file')) {
            if ($user->cnic_front_image) Storage::disk('public')->delete($user->cnic_front_image);
            $validated['cnic_front_image'] = $this->storeFile($request->file('cnic_front_file'), 'vendors/documents', 'cnic_f');
        }
        if ($request->hasFile('cnic_back_file')) {
            if ($user->cnic_back_image) Storage::disk('public')->delete($user->cnic_back_image);
            $validated['cnic_back_image'] = $this->storeFile($request->file('cnic_back_file'), 'vendors/documents', 'cnic_b');
        }

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => $user->fresh(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect',
            ], 422);
        }
        $user->update(['password' => Hash::make($validated['new_password'])]);
        return response()->json([
            'status' => 'success',
            'message' => 'Password updated successfully',
        ]);
    }
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|regex:/^\+?[0-9]{10,15}$/|unique:vendors,phone',
            'email' => 'nullable|email|max:255|unique:vendors,email',
            'password' => 'required|string|min:8|confirmed',
            
            // New fields
            'cnic' => 'required|string|max:20',
            'dob' => 'required|date',
            'experience' => 'required|string|max:255',
            'handled_categories' => 'required|array', // List of category IDs or names
            
            // Image uploads
            'profile_image_file' => 'nullable|image|max:2048',
            'cnic_front_file' => 'required|image|max:2048',
            'cnic_back_file' => 'required|image|max:2048',
        ]);

        // Handle Image Uploads
        $profileImagePath = $request->hasFile('profile_image_file') 
            ? $this->storeFile($request->file('profile_image_file'), 'vendors/profiles', 'profile') 
            : null;
            
        $cnicFrontPath = $this->storeFile($request->file('cnic_front_file'), 'vendors/documents', 'cnic_f');
        $cnicBackPath = $this->storeFile($request->file('cnic_back_file'), 'vendors/documents', 'cnic_b');

        $vendor = Vendor::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => 'pending', // Usually new registrations should be pending for admin review
            
            'cnic' => $validated['cnic'],
            'dob' => $validated['dob'],
            'experience' => $validated['experience'],
            'handled_categories' => $validated['handled_categories'],
            
            'profile_image' => $profileImagePath,
            'cnic_front_image' => $cnicFrontPath,
            'cnic_back_image' => $cnicBackPath,
            'joining_date' => now(),
        ]);

        $token = $vendor->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful. Your account is pending review.',
            'token' => $token,
            'data' => $vendor,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|regex:/^\+?[0-9]{10,15}$/|exists:vendors,phone',
            'password' => 'required|string|min:8',
        ]);

        $vendor = Vendor::where('phone', $validated['phone'])->first();

        if (! $vendor || ! Hash::check($validated['password'], $vendor->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        if ($vendor->status === 'inactive') {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is inactive/suspended.',
            ], 403);
        }

        $vendor->tokens()->where('name', 'auth_token')->delete();
        $token = $vendor->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $userData = $user->toArray();
        $userData['user_type'] = 'vendor';
        return response()->json([
            'status' => 'success',
            'user' => $userData,
        ]);
    }

    public function signOut(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ], 200);
    }
}
