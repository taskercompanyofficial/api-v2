<?php

namespace App\Http\Controllers\Auth\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:vendors,email,' . $user->id,
            'phone' => 'sometimes|required|regex:/^\+?[0-9]{10,15}$/|unique:vendors,phone,' . $user->id,
        ]);
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
        ]);

        $vendor = Vendor::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        $token = $vendor->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful',
            'token' => $token,
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

        if ($vendor->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is not active',
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
        return response()->json([
            'status' => 'success',
            'user' => $user,
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
