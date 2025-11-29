<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Permission;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserPermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        
        if ($userId) {
            $userPermissions = UserPermission::where('user_id', $userId)
                ->with(['permission', 'user:id,name,email'])
                ->get();
        } else {
            $userPermissions = UserPermission::with(['permission', 'user:id,name,email'])
                ->get();
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $userPermissions
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_id' => 'required|exists:permissions,id',
            'status' => 'boolean',
        ]);
        
        // Check if the user-permission combination already exists
        $exists = UserPermission::where('user_id', $validated['user_id'])
            ->where('permission_id', $validated['permission_id'])
            ->exists();
            
        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'This permission is already assigned to the user'
            ], 422);
        }
        
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();
        
        $userPermission = UserPermission::create($validated);
        $userPermission->load(['permission', 'user:id,name,email']);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Permission assigned to user successfully',
            'data' => $userPermission
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $userPermission = UserPermission::with(['permission', 'user:id,name,email', 'createdBy:id,name', 'updatedBy:id,name'])
            ->findOrFail($id);
            
        return response()->json([
            'status' => 'success',
            'data' => $userPermission
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $userPermission = UserPermission::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);
        
        $validated['updated_by'] = Auth::id();
        
        $userPermission->update($validated);
        $userPermission->load(['permission', 'user:id,name,email']);
        
        return response()->json([
            'status' => 'success',
            'message' => 'User permission updated successfully',
            'data' => $userPermission
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $userPermission = UserPermission::findOrFail($id);
        $userPermission->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Permission removed from user successfully'
        ]);
    }
    
    /**
     * Assign multiple permissions to a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);
        
        $userId = $validated['user_id'];
        $permissionIds = $validated['permission_ids'];
        $authUserId = Auth::id();
        
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Get existing permission IDs for this user
            $existingPermissionIds = UserPermission::where('user_id', $userId)
                ->pluck('permission_id')
                ->toArray();
            
            // Determine which permissions to add (not already assigned)
            $newPermissionIds = array_diff($permissionIds, $existingPermissionIds);
            
            // Create new user permission records
            $userPermissions = [];
            foreach ($newPermissionIds as $permissionId) {
                $userPermissions[] = [
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'status' => true,
                    'created_by' => $authUserId,
                    'updated_by' => $authUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            if (!empty($userPermissions)) {
                UserPermission::insert($userPermissions);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => count($newPermissionIds) . ' permissions assigned to user successfully',
                'added_permissions' => $newPermissionIds
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign permissions: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sync permissions for a user (replace existing permissions with new set).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncPermissions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);
        
        $userId = $validated['user_id'];
        $permissionIds = $validated['permission_ids'];
        
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Delete all existing permissions for this user
            UserPermission::where('user_id', $userId)->delete();
            
            // Create new user permission records
            $authUserId = Auth::id();
            $userPermissions = [];
            
            foreach ($permissionIds as $permissionId) {
                $userPermissions[] = [
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'status' => true,
                    'created_by' => $authUserId,
                    'updated_by' => $authUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            if (!empty($userPermissions)) {
                UserPermission::insert($userPermissions);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'User permissions synchronized successfully',
                'permission_count' => count($permissionIds)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to sync permissions: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all permissions for a user (including role permissions).
     *
     * @param  int  $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPermissions($userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $permissions = $user->getAllPermissions();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'slug' => $user->role->slug,
                    ] : null,
                ],
                'permissions' => $permissions,
                'direct_permissions_count' => $user->permissions()->count(),
                'role_permissions_count' => $user->role ? $user->role->permissions()->count() : 0,
                'total_permissions_count' => $permissions->count(),
            ]
        ]);
    }
}
