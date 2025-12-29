<?php

namespace App\Http\Controllers\Auth\CRM;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function checkCredentials(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:staff,crm_login_email',
            'password' => 'required|min:8',
        ]);


        if (Auth::attempt(['crm_login_email' => $request->email, 'password' => $request->password])) {
            $staff = Auth::user();
            
            // Check if staff has CRM access
            if (!$staff->has_access_in_crm) {
                Auth::logout();
                return response()->json([
                    'message' => 'You do not have access to the CRM system.',
                ], 403);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Logged in successfully',
            ]);
        }

        return response()->json([
            'message' => 'The provided credentials do not match our records.',
        ], 401);
    }
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);


        if (Auth::attempt(['crm_login_email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            
            // Check if staff has CRM access
            if (!$user->has_access_in_crm) {
                Auth::logout();
                return response()->json([
                    'message' => 'You do not have access to the CRM system.',
                ], 403);
            }
            
            $oldToken = $user->tokens()->where('name', 'auth_token')->first();
            if ($oldToken) {
                $oldToken->delete();
            }
            $token = $user->createToken('auth_token')->plainTextToken;
            $user->token = $token;
            return response()->json([
                'status' => 'success',
                'message' => 'Logged in successfully',
                'user' => $user,
            ]);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }
    public function me(Request $request)
    {
        $staff = $request->user();
        $staff = Staff::with(['role', 'status'])->find($staff->id);

        // Get permissions separately to avoid the addEagerConstraints error
        $permissions = $staff->getAllPermissions();

        // Get permitted routes for the sidebar navigation
        $routes = $staff->getPermittedRoutes();

        // Format permissions for frontend use
        $formattedPermissions = $permissions->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'description' => $permission->description,
                'status' => $permission->status,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
                'pivot' => $permission->pivot ? [
                    'role_id' => $permission->pivot->role_id ?? null,
                    'permission_id' => $permission->pivot->permission_id ?? null,
                    'status' => $permission->pivot->status ?? null,
                    'created_by' => $permission->pivot->created_by ?? null,
                    'updated_by' => $permission->pivot->updated_by ?? null,
                    'created_at' => $permission->pivot->created_at ?? null,
                    'updated_at' => $permission->pivot->updated_at ?? null,
                ] : null
            ];
        });

        return response()->json([
            'user' => $staff,
            'permissions' => $formattedPermissions,
            'routes' => $routes
        ], 200);
    }


    /**
     * Get the sidebar navigation routes for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sidebar(Request $request)
    {
        $user = $request->user();
        $routes = $user->getPermittedRoutes();

        return response()->json([
            'status' => 'success',
            'data' => $routes
        ], 200);
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
