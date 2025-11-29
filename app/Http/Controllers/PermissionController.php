<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::with(['createdBy:id,name', 'updatedBy:id,name'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * 
     * This method is not used in API context, but kept for resource controller compatibility.
     */
    public function create()
    {
        abort(404);
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
            'name' => 'required|string|max:255|unique:permissions,name',
            'description' => 'nullable|string|max:255',
            'status' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $permission = Permission::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Permission $permission): JsonResponse
    {
        $permission->load(['createdBy:id,name', 'updatedBy:id,name', 'roles', 'users']);

        return response()->json([
            'status' => 'success',
            'data' => $permission
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * 
     * This method is not used in API context, but kept for resource controller compatibility.
     *
     * @param  \App\Models\Permission  $permission
     */
    public function edit(Permission $permission)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:permissions,name,' . $permission->id,
            'description' => 'nullable|string|max:255',
            'status' => 'sometimes|boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $validated['updated_by'] = Auth::id();

        $permission->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Permission $permission): JsonResponse
    {
        // Check if permission is associated with any roles or users
        if ($permission->roles()->count() > 0 || $permission->users()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete permission that is assigned to roles or users'
            ], 422);
        }

        // Check if permission is associated with any routes
        if ($permission->routes()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete permission that is assigned to routes'
            ], 422);
        }

        $permission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Permission deleted successfully'
        ]);
    }

    public function verifyPermission(string $permission): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $permissionRecord = Permission::where('slug', $permission)->first();
        if (!$permissionRecord) {
            return response()->json(['status' => 'error', 'message' => 'Permission not found'], 404);
        }

        // Get all permissions for this user using the getAllPermissions method
        $userPermissions = $user->getAllPermissions();

        // Check if the user has the required permission
        $hasPermission = $userPermissions->contains('slug', $permission);

        if ($hasPermission) {
            return response()->json(['status' => 'success', 'message' => 'User has the required permission']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'User does not have the required permission'], );
        }
    }
}
