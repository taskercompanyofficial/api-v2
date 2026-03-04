<?php

namespace App\Http\Controllers\Authenticated\StaffApp;

use App\Http\Controllers\Controller;
use App\Models\StaffTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class StaffTaskController extends Controller
{
    /**
     * List all tasks assigned to the authenticated staff member.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $staff = $request->user();
            $query = StaffTask::where('staff_id', $staff->id)
                ->with('assignedBy:id,name')
                ->orderByRaw("FIELD(status, 'in_progress', 'pending', 'completed')")
                ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
                ->orderBy('due_date', 'asc');

            // Optional filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $tasks = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $tasks,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get summary counts for the staff member's tasks.
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $staff = $request->user();
            $tasks = StaffTask::where('staff_id', $staff->id);

            $summary = [
                'total' => (clone $tasks)->count(),
                'pending' => (clone $tasks)->where('status', 'pending')->count(),
                'in_progress' => (clone $tasks)->where('status', 'in_progress')->count(),
                'completed' => (clone $tasks)->where('status', 'completed')->count(),
                'overdue' => (clone $tasks)->where('status', '!=', 'completed')
                    ->where('due_date', '<', now())
                    ->whereNotNull('due_date')
                    ->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $summary,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Staff can only update the status of a task (no edit, no delete).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        try {
            $staff = $request->user();
            $task = StaffTask::where('staff_id', $staff->id)->findOrFail($id);

            $task->status = $request->status;
            if ($request->status === 'completed') {
                $task->completed_at = now();
            } else {
                $task->completed_at = null;
            }
            $task->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Task status updated',
                'data' => $task->load('assignedBy:id,name'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
