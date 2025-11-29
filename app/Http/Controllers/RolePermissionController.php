<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $roleId = $request->query('role_id');

        if ($roleId) {
            $rolePermissions = RolePermission::where('role_id', $roleId)
                ->with(['permission', 'role'])
                ->get();
        } else {
            $rolePermissions = RolePermission::with(['permission', 'role'])
                ->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $rolePermissions
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
            'role_id' => 'required|exists:roles,id',
            'permission_id' => 'required|exists:permissions,id',
            'status' => 'boolean',
        ]);

        // Check if the role-permission combination already exists
        $exists = RolePermission::where('role_id', $validated['role_id'])
            ->where('permission_id', $validated['permission_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'This permission is already assigned to the role'
            ], 422);
        }

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $rolePermission = RolePermission::create($validated);
        $rolePermission->load(['permission', 'role']);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission assigned to role successfully',
            'data' => $rolePermission
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
        $rolePermission = RolePermission::with(['permission', 'role', 'createdBy', 'updatedBy'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $rolePermission
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
        $rolePermission = RolePermission::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);

        $validated['updated_by'] = Auth::id();

        $rolePermission->update($validated);
        $rolePermission->load(['permission', 'role']);

        return response()->json([
            'status' => 'success',
            'message' => 'Role permission updated successfully',
            'data' => $rolePermission
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
        $rolePermission = RolePermission::findOrFail($id);
        $rolePermission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Permission removed from role successfully'
        ]);
    }

    /**
     * Assign multiple permissions to a role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $roleId = $validated['role_id'];
        $permissionIds = $validated['permission_ids'];
        $userId = Auth::id();

        // Begin transaction
        DB::beginTransaction();

        try {
            // Get existing permission IDs for this role
            $existingPermissionIds = RolePermission::where('role_id', $roleId)
                ->pluck('permission_id')
                ->toArray();

            // Determine which permissions to add (not already assigned)
            $newPermissionIds = array_diff($permissionIds, $existingPermissionIds);

            // Create new role permission records
            $rolePermissions = [];
            foreach ($newPermissionIds as $permissionId) {
                $rolePermissions[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'status' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($rolePermissions)) {
                RolePermission::insert($rolePermissions);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => count($newPermissionIds) . ' permissions assigned to role successfully',
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
     * Sync permissions for a role (replace existing permissions with new set).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncPermissions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $roleId = $validated['role_id'];
        $permissionIds = $validated['permission_ids'] ?? [];

        // Begin transaction
        DB::beginTransaction();

        try {
            // Delete all existing permissions for this role
            RolePermission::where('role_id', $roleId)->delete();

            // Create new role permission records if any permissions were provided
            $userId = Auth::id();
            $rolePermissions = [];

            foreach ($permissionIds as $permissionId) {
                $rolePermissions[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'status' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($rolePermissions)) {
                RolePermission::insert($rolePermissions);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Role permissions synchronized successfully',
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
}
