<?php

namespace App\Http\Controllers\Auth\VendorStaff;

use App\Http\Controllers\Controller;
use App\Models\VendorStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Staff login via phone + password
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $staff = VendorStaff::where('phone', $validated['phone'])->first();

        if (! $staff || ! Hash::check($validated['password'], $staff->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid phone number or password',
            ], 401);
        }

        if ($staff->status !== 'approved') {
            $statusMessage = match($staff->status) {
                'pending' => 'Your account is pending approval from your vendor.',
                'rejected' => 'Your account has been rejected. Contact your vendor.',
                default => 'Your account is not active.',
            };
            return response()->json([
                'status' => 'error',
                'message' => $statusMessage,
            ], 403);
        }

        // Clear old tokens
        $staff->tokens()->where('name', 'staff_auth_token')->delete();
        $token = $staff->createToken('staff_auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $token,
            'user_type' => 'vendor_staff',
        ]);
    }

    /**
     * Get current staff profile
     */
    public function me(Request $request)
    {
        $staff = $request->user();
        $staff->load('vendor:id,name,company_name,phone');

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'phone' => $staff->phone,
                'email' => $staff->email,
                'status' => $staff->status,
                'vendor_id' => $staff->vendor_id,
                'vendor' => $staff->vendor,
                'user_type' => 'vendor_staff',
                'created_at' => $staff->created_at,
                'updated_at' => $staff->updated_at,
            ],
        ]);
    }

    /**
     * Sign out
     */
    public function signOut(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string|min:6',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $staff = $request->user();

        if (!Hash::check($validated['current_password'], $staff->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $staff->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password updated successfully',
        ]);
    }
}
