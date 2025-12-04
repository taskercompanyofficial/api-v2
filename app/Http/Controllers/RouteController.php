<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RouteController extends Controller
{

    public function index(Request $request)
    {
        $path = $request->query('path') ?? null;
        $routes = Route::with(['parent', 'children', 'permission', 'createdBy:id,name', 'updatedBy:id,name'])
            ->orderBy('order')
            ->when($path, function ($query) use ($path) {
                $query->where('path', $path);
            })
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $routes
        ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:255',
            'method' => 'nullable|string|max:10',
            'path' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'parent_id' => 'nullable|exists:routes,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $slug = Str::slug($payload['name']);
        $original = $slug;
        $i = 1;
        while (Route::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i;
            $i++;
        }

        // FIX: Permissions were not generating uniquely before (bad slug key), and there was confusion in names/slugs.
        // Each permission must have a unique slug, usually based on action + route-slug (not just action or action+name).
        // Also, ensure that permission creation happens *before* Route is stored so permission IDs can be used.
        $permissionIds = [];
        if (!empty($payload['permissions']) && is_array($payload['permissions'])) {
            foreach ($payload['permissions'] as $permName) {
                $permAction = trim(strtolower($permName));
                if (!$permAction || !is_string($permName)) continue;

                // Permission slug should be in the form of: can-action-route-slug (not just action or action + name)
                $permSlug = 'can-' . $permAction . '-' . $slug;

                // Name like: CAN_ACTION_ROUTE
                $permLabel = strtoupper($permAction) . ' ' . $payload['name'];
                $permission = Permission::firstOrCreate(
                    ['slug' => $permSlug],
                    [
                        'name' => $permLabel,
                        'slug' => $permSlug,
                        'description' => $permLabel . ' permission for route ' . $payload['name'],
                        'status' => 1,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]
                );
                $permissionIds[] = $permission->id;
            }
        }

        $mainPermissionId = count($permissionIds) > 0 ? $permissionIds[0] : null;

        try {
            $route = Route::create(array_merge(
                $payload,
                [
                    'slug' => $slug,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'permission_id' => $mainPermissionId,
                ]
            ));

            return response()->json([
                'status' => 'success',
                'message' => 'Route created successfully',
                'slug' => $route->slug,
                'route_id' => $route->id,
                'main_permission_id' => $mainPermissionId,
                'all_permission_ids' => $permissionIds,
            ]);
        } catch (\Throwable $err) {
            return response()->json(['status' => 'error', 'message' => $err->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Route  $route
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Route $route): JsonResponse
    {
        $route->load(['parent', 'children', 'permission', 'createdBy', 'updatedBy']);

        return response()->json([
            'status' => 'success',
            'data' => $route
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Route  $route
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Route $route): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:routes,slug,' . $route->id,
            'method' => 'nullable|string|max:10',
            'path' => 'sometimes|required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'parent_id' => 'nullable|exists:routes,id',
            'permission_id' => 'nullable|exists:permissions,id',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $route->update(array_merge(
            $request->all(),
            ['updated_by' => Auth::id()]
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Route updated successfully',
            'data' => $route->fresh()->load(['parent', 'permission'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Route  $route
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Route $route): JsonResponse
    {
        // Check if route has children
        if ($route->children()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete route with children. Delete children first.'
            ], 422);
        }

        $route->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Route deleted successfully'
        ]);
    }

    /**
     * Get all routes as a tree structure.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tree(): JsonResponse
    {
        $routes = Route::with(['children', 'permission'])
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $routes
        ]);
    }

    /**
     * Get available permissions for routes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('status', true)->get();

        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ]);
    }
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.id' => 'required|exists:routes,id',
            'data.*.name' => 'nullable|string|max:255',
            'data.*.slug' => 'nullable|string|max:255',
            'data.*.path' => 'nullable|string|max:255',
            'data.*.description' => 'nullable|string',
            'data.*.icon' => 'nullable|string|max:255',
            'data.*.order' => 'nullable|string',
            'data.*.parent_id' => 'nullable|exists:routes,id',
            'data.*.permission_id' => 'nullable|exists:permissions,id',
            'data.*.status' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $affected = 0;
        $userId = Auth::id();

        // Update each route individually from the data array
        foreach ($request->data as $routeData) {
            if (isset($routeData['id'])) {
                $route = Route::find($routeData['id']);
                if ($route) {
                    $updateData = array_filter($routeData, function ($value, $key) {
                        // Exclude id, created_by, updated_by fields and null values
                        return !in_array($key, ['id', 'created_by', 'updated_by']) && $value !== null;
                    }, ARRAY_FILTER_USE_BOTH);

                    // Make sure we're storing just the ID as an integer for updated_by
                    if ($userId) {
                        $updateData['updated_by'] = (int) $userId;
                    }

                    // Use update without timestamps to avoid issues with created_at/updated_at
                    $route->timestamps = false;
                    $route->update($updateData);
                    $route->timestamps = true;

                    $affected++;
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "{$affected} routes updated successfully"
        ]);
    }
}
