<?php

namespace App\Http\Controllers\Authenticated\StaffApp;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated staff
     */
    public function index(Request $request)
    {
        try {
            $staff = Auth::user();
            
            $notifications = Notification::where('user_id', $staff->id)
                ->where('user_type', 'App\Models\Staff')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $notifications,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $staff = Auth::user();
            
            $notification = Notification::where('user_id', $staff->id)
                ->where('user_type', 'App\Models\Staff')
                ->where('id', $id)
                ->firstOrFail();

            $notification->markAsRead();

            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read',
                'data' => $notification,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $staff = Auth::user();
            
            Notification::where('user_id', $staff->id)
                ->where('user_type', 'App\Models\Staff')
                ->where('read', false)
                ->update([
                    'read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'status' => 'success',
                'message' => 'All notifications marked as read',
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Get unread count
     */
    public function unreadCount(Request $request)
    {
        try {
            $staff = Auth::user();
            
            $count = Notification::where('user_id', $staff->id)
                ->where('user_type', 'App\Models\Staff')
                ->where('read', false)
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => ['count' => $count],
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Register device token for push notifications
     */
    public function registerToken(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
            ]);

            $staff = Auth::user();
            $staff->device_token = $request->token;
            $staff->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Device token registered successfully',
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }
}
