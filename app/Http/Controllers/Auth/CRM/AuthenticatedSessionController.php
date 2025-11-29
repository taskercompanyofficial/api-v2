<?php

namespace App\Http\Controllers\Auth\CRM;

use App\Http\Controllers\Controller;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;

class AuthenticatedSessionController extends Controller
{
    public function checkCredentials(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8',
        ]);


        if (Auth::attempt($request->only('email', 'password'))) {
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


        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
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
        $user = $request->user();
        $user = User::with(['role', 'status'])->find($user->id);

        // Get permissions separately to avoid the addEagerConstraints error
        $permissions = $user->getAllPermissions();

        // Get permitted routes for the sidebar navigation
        $routes = $user->getPermittedRoutes();

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
            'user' => $user,
            'permissions' => $formattedPermissions,
            'routes' => $routes
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
