<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RoutePermissionController extends Controller
{
    /**
     * Display a listing of routes with their permissions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $routes = Route::with(['permission', 'parent', 'children'])
            ->orderBy('order')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $routes
        ]);
    }
    
    /**
     * Display a listing of routes grouped by parent.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRouteTree(): JsonResponse
    {
        $parentRoutes = Route::with(['permission', 'children.permission'])
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $parentRoutes
        ]);
    }
    
    /**
     * Update the permission for a route.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $routeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRoutePermission(Request $request, $routeId): JsonResponse
    {
        $route = Route::findOrFail($routeId);
        
        $validated = $request->validate([
            'permission_id' => 'nullable|exists:permissions,id',
        ]);
        
        $route->permission_id = $validated['permission_id'];
        $route->updated_by = Auth::id();
        $route->save();
        
        $route->load('permission');
        
        return response()->json([
            'status' => 'success',
            'message' => 'Route permission updated successfully',
            'data' => $route
        ]);
    }
    
    /**
     * Get all permissions that can be assigned to routes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailablePermissions(): JsonResponse
    {
        $permissions = Permission::where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);
            
        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ]);
    }
    
    /**
     * Batch update permissions for multiple routes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchUpdatePermissions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'routes' => 'required|array',
            'routes.*.id' => 'required|exists:routes,id',
            'routes.*.permission_id' => 'nullable|exists:permissions,id',
        ]);
        
        $updatedRoutes = [];
        $userId = Auth::id();
        
        foreach ($validated['routes'] as $routeData) {
            $route = Route::find($routeData['id']);
            
            if ($route) {
                $route->permission_id = $routeData['permission_id'];
                $route->updated_by = $userId;
                $route->save();
                
                $updatedRoutes[] = $route->id;
            }
        }
        
        return response()->json([
            'status' => 'success',
            'message' => count($updatedRoutes) . ' routes updated successfully',
            'updated_routes' => $updatedRoutes
        ]);
    }
}
