<?php

namespace App\Http\Controllers\Auth\TcChat;

use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\Log;
use Illuminate\Http\Request;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
class AuthenticatedSessionController extends Controller
{
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

            // Check staff status - only active staff can login
            $staffWithStatus = Staff::with('status')->find($user->id);
            if (!$staffWithStatus->status || $staffWithStatus->status->slug !== 'active') {
                Auth::logout();
                $statusMessage = $staffWithStatus->status ? $staffWithStatus->status->name : 'Unknown';
                return response()->json([
                    'message' => "Your account is currently {$statusMessage}. Please contact your administrator.",
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->token = $token;
            return response()->json([
                'status' => 'success',
                'message' => 'Logged in successfully2',
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
        return response()->json([
            'status' => 'success',
            'user' => $user,
        ]);
    }

    public function updateNotificationSettings(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'push_enabled' => 'nullable|boolean',
            'email_enabled' => 'nullable|boolean',
            'sound_enabled' => 'nullable|boolean',
            'vibrate_enabled' => 'nullable|boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification settings updated successfully',
            'user' => $user,
        ]);
    }
    public function destroy(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }
}
