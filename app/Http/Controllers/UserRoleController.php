<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserRoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->input('user_id');
        
        if ($userId) {
            $user = User::with('role')->findOrFail($userId);
            return response()->json([
                'status' => 'success',
                'data' => $user->role
            ]);
        }
        
        $users = User::with('role')->get();
        return response()->json([
            'status' => 'success',
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ];
            })
        ]);
    }

    /**
     * Assign a role to a user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);
        
        $user = User::findOrFail($validated['user_id']);
        $user->role_id = $validated['role_id'];
        $user->updated_by = Auth::id();
        $user->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Role assigned successfully',
            'data' => $user->load('role')
        ]);
    }

    /**
     * Update the role for a specific user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);
        
        $user->role_id = $validated['role_id'];
        $user->updated_by = Auth::id();
        $user->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully',
            'data' => $user->load('role')
        ]);
    }

    /**
     * Remove the role from a user.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->role_id = null;
        $user->updated_by = Auth::id();
        $user->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Role removed successfully'
        ]);
    }
}
