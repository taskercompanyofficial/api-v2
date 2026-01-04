<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('perPage', 20);
            $type = $request->get('type'); // Filter by type if provided

            $query = DB::table('notifications')
                ->where('notifiable_id', auth()->id())
                ->where('notifiable_type', 'App\\Models\\Staff')
                ->orderBy('created_at', 'desc');

            if ($type) {
                $query->where('type', 'like', "%{$type}%");
            }

            $notifications = $query->paginate($perPage);

            // Transform notifications
            $transformedNotifications = collect($notifications->items())->map(function ($notification) {
                $data = json_decode($notification->data, true);
                
                return [
                    'id' => $notification->id,
                    'type' => $this->extractNotificationType($notification->type),
                    'title' => $data['title'] ?? 'Notification',
                    'message' => $data['message'] ?? '',
                    'data' => $data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'link' => $data['link'] ?? null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $transformedNotifications,
                'pagination' => [
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch notifications: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(string $id): JsonResponse
    {
        try {
            $updated = DB::table('notifications')
                ->where('id', $id)
                ->where('notifiable_id', auth()->id())
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            if (!$updated) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found or already read',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark notification as read: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            DB::table('notifications')
                ->where('notifiable_id', auth()->id())
                ->where('notifiable_type', 'App\\Models\\Staff')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'status' => 'success',
                'message' => 'All notifications marked as read',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark all notifications as read: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $deleted = DB::table('notifications')
                ->where('id', $id)
                ->where('notifiable_id', auth()->id())
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Notification deleted',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(): JsonResponse
    {
        try {
            $count = DB::table('notifications')
                ->where('notifiable_id', auth()->id())
                ->where('notifiable_type', 'App\\Models\\Staff')
                ->whereNull('read_at')
                ->count();

            return response()->json([
                'status' => 'success',
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get unread count: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract notification type from class name
     */
    private function extractNotificationType(string $className): string
    {
        // Extract the last part of the class name
        $parts = explode('\\', $className);
        $type = end($parts);
        
        // Convert from PascalCase to kebab-case
        $type = preg_replace('/([a-z])([A-Z])/', '$1-$2', $type);
        $type = strtolower($type);
        
        // Map to our notification types
        if (str_contains($type, 'work-order') || str_contains($type, 'workorder')) {
            return 'work-order';
        } elseif (str_contains($type, 'part')) {
            return 'part-request';
        } elseif (str_contains($type, 'assign')) {
            return 'assignment';
        } elseif (str_contains($type, 'reminder')) {
            return 'reminder';
        } elseif (str_contains($type, 'document') || str_contains($type, 'file')) {
            return 'document';
        }
        
        return 'system';
    }
}
