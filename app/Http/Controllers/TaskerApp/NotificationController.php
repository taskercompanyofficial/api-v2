<?php

namespace App\Http\Controllers\TaskerApp;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated customer
     */
    public function index(Request $request)
    {
        try {
            $customer = $request->user();
            
            $notifications = Notification::where('user_id', $customer->id)
                ->where('user_type', 'App\Models\Customer')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($n) {
                    return [
                        'id' => $n->id,
                        'title' => $n->title,
                        'message' => $n->message,
                        'type' => $n->type,
                        'data' => $n->data,
                        'read' => (bool)$n->read,
                        'read_at' => $n->read_at,
                        'created_at' => $n->created_at,
                    ];
                });

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
            $customer = $request->user();
            
            $notification = Notification::where('user_id', $customer->id)
                ->where('user_type', 'App\Models\Customer')
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
            $customer = $request->user();
            
            Notification::where('user_id', $customer->id)
                ->where('user_type', 'App\Models\Customer')
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
            $customer = $request->user();
            
            $count = Notification::where('user_id', $customer->id)
                ->where('user_type', 'App\Models\Customer')
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
}
