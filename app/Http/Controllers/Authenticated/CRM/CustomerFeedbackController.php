<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\CustomerFeedback;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerFeedbackController extends Controller
{
    /**
     * Get all feedbacks for a work order
     */
    public function index(Request $request, string $workOrderId): JsonResponse
    {
        try {
            $feedbacks = CustomerFeedback::where('work_order_id', $workOrderId)
                ->with(['customer:id,name', 'createdBy:id,first_name,last_name', 'updatedBy:id,first_name,last_name'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $feedbacks,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch feedbacks: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new feedback
     */
    public function store(Request $request, string $workOrderId): JsonResponse
    {
        $validator = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback_type' => 'required|in:service_quality,technician_behavior,timeliness,overall',
            'remarks' => 'required|string|max:1000',
        ]);

    $user =$request->user();
        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);

            $feedback = CustomerFeedback::create([
                'work_order_id' => $workOrder->id,
                'customer_id' => $workOrder->customer_id,
                'rating' => $request->rating,
                'feedback_type' => $request->feedback_type,
                'remarks' => $request->remarks,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $feedback->load(['customer:id,name', 'createdBy:id,first_name,last_name', 'updatedBy:id,first_name,last_name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Feedback created successfully',
                'data' => $feedback,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create feedback: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a feedback
     */
    public function update(Request $request, string $workOrderId, string $id): JsonResponse
    {
        $validator = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'feedback_type' => 'sometimes|required|in:service_quality,technician_behavior,timeliness,overall',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $user =$request->user();
        try {
            $feedback = CustomerFeedback::where('work_order_id', $workOrderId)
                ->findOrFail($id);

            $feedback->update([
                'rating' => $request->rating ?? $feedback->rating,
                'feedback_type' => $request->feedback_type ?? $feedback->feedback_type,
                'remarks' => $request->remarks ?? $feedback->remarks,
                'updated_by' => $user->id,
            ]);

            $feedback->load(['customer:id,name', 'createdBy:id,first_name,last_name', 'updatedBy:id,first_name,last_name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Feedback updated successfully',
                'data' => $feedback,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update feedback: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a feedback
     */
    public function destroy(string $workOrderId, string $id): JsonResponse
    {
        try {
            $feedback = CustomerFeedback::where('work_order_id', $workOrderId)
                ->findOrFail($id);

            $feedback->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Feedback deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete feedback: ' . $e->getMessage(),
            ], 500);
        }
    }
}
