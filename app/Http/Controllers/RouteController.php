<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
            'slug' => 'required|string|max:255|unique:routes',
            'method' => 'nullable|string|max:10',
            'path' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'parent_id' => 'nullable|exists:routes,id',
            'permission_id' => 'nullable|exists:permissions,id',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        try {
            $route = Route::create($payload);

            return response()->json([
                'status' => 'success',
                'message' => 'Route created successfully',
                'data' => $route->load(['parent', 'permission'])
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage()
            ], 500);
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
