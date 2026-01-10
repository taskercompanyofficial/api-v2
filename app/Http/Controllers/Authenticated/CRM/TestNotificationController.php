<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Staff;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;

class TestNotificationController extends Controller
{
    /**
     * Send test notification
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        try {
            $title = $request->input('title', 'Test Real-time Notification');
            $message = $request->input('message', 'If you see this, real-time notifications are working! 🚀');
            $type = $request->input('type', 'system');
            $userId = $request->input('user_id'); // Optional specific user ID

            if ($userId) {
                // Specific user approach
                $staff = Staff::find($userId);
                if (!$staff) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Staff member not found',
                    ], 404);
                }

                Notification::createNotification(
                    $staff->id,
                    'App\Models\Staff',
                    $title,
                    $message,
                    $type,
                    ['link' => '/crm/dashboard']
                );

                return response()->json([
                    'status' => 'success',
                    'message' => "Test notification sent to {$staff->first_name} {$staff->last_name}",
                ]);
            } else {
                // All users approach
                $staffMembers = Staff::where('status_id', 1)->get(); // Assuming 1 is active

                foreach ($staffMembers as $member) {
                    Notification::createNotification(
                        $member->id,
                        'App\Models\Staff',
                        $title,
                        $message,
                        $type,
                        ['link' => '/crm/dashboard']
                    );
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Test notification sent to all active staff members (' . $staffMembers->count() . ')',
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send work order update notification
     */
    public function sendWorkOrderNotification(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id', Auth::user()->id);
            $updaterName = $request->input('updater_name', 'Muhammad Shehzad');

            // Get the last work order
            $workOrder = WorkOrder::orderBy('id', 'desc')->first();

            if (!$workOrder) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No work orders found',
                ], 404);
            }

            $staff = Staff::find($userId);
            if (!$staff) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Staff member not found',
                ], 404);
            }

            $title = "Work Order #{$workOrder->code} Updated";
            $message = "{$updaterName} updated work order #{$workOrder->code}. Click to view details.";
            $link = "/crm/work-orders/{$workOrder->id}";

            Notification::createNotification(
                $staff->id,
                'App\Models\Staff',
                $title,
                $message,
                'work-order',
                [
                    'link' => $link,
                    'work_order_id' => $workOrder->id,
                    'work_order_code' => $workOrder->code,
                    'updater_name' => $updaterName,
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => "Work order notification sent to {$staff->first_name} {$staff->last_name}",
                'data' => [
                    'work_order_id' => $workOrder->id,
                    'work_order_code' => $workOrder->code,
                    'link' => $link,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send work order notification: ' . $e->getMessage(),
            ], 500);
        }
    }
}
