<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\ServiceCenterUnit;
use App\Models\WorkOrder;
use App\Services\ServiceCenterUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class ServiceCenterUnitController extends Controller
{
    protected ServiceCenterUnitService $service;

    public function __construct(ServiceCenterUnitService $service)
    {
        $this->service = $service;
    }

    /**
     * Get service center unit for a work order
     */
    public function show(int $workOrderId): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $unit = $workOrder->serviceCenterUnit;

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No service center unit found for this work order',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $unit->load([
                    'pickedUpBy:id,first_name,last_name',
                    'receivedByStaff:id,first_name,last_name',
                    'repairedBy:id,first_name,last_name',
                    'deliveredByStaff:id,first_name,last_name',
                    'history.performedByStaff:id,first_name,last_name',
                ]),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send work order unit to service center
     */
    public function sendToServiceCenter(Request $request, int $workOrderId): JsonResponse
    {
        $request->validate([
            'unit_serial_number' => 'nullable|string|max:255',
            'unit_model' => 'nullable|string|max:255',
            'unit_type' => 'nullable|string|max:100',
            'unit_condition_on_arrival' => 'nullable|string',
            'estimated_completion_date' => 'nullable|date',
        ]);

        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $userId = Auth::id();

            $unit = $this->service->sendToServiceCenter($workOrder, $request->all(), $userId);

            return response()->json([
                'success' => true,
                'message' => 'Unit sent to service center successfully',
                'data' => $unit->load('history'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update service center unit details
     */
    public function update(Request $request, int $workOrderId): JsonResponse
    {
        $request->validate([
            'unit_serial_number' => 'nullable|string|max:255',
            'unit_model' => 'nullable|string|max:255',
            'unit_type' => 'nullable|string|max:100',
            'bay_number' => 'nullable|string|max:50',
            'diagnosis_notes' => 'nullable|string',
            'repair_notes' => 'nullable|string',
            'parts_used' => 'nullable|array',
            'estimated_completion_date' => 'nullable|date',
        ]);

        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $unit = $workOrder->serviceCenterUnit;

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No service center unit found',
                ], 404);
            }

            $unit->update([
                ...$request->only([
                    'unit_serial_number',
                    'unit_model',
                    'unit_type',
                    'bay_number',
                    'diagnosis_notes',
                    'repair_notes',
                    'parts_used',
                    'estimated_completion_date',
                ]),
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Service center unit updated successfully',
                'data' => $unit->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update service center unit status
     */
    public function updateStatus(Request $request, int $workOrderId): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:' . implode(',', array_keys(ServiceCenterUnit::getStatusLabels())),
            'notes' => 'nullable|string',
        ]);

        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $unit = $workOrder->serviceCenterUnit;

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No service center unit found',
                ], 404);
            }

            $unit = $this->service->updateStatus($unit, $request->status, $request->notes, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'data' => $unit->load('history'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark unit as picked up
     */
    public function markAsPickedUp(Request $request, int $workOrderId): JsonResponse
    {
        $request->validate([
            'pickup_date' => 'nullable|date',
            'pickup_time' => 'nullable',
            'pickup_location' => 'nullable|string',
            'pickup_photos' => 'nullable|array',
            'unit_condition' => 'nullable|string',
        ]);

        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $unit = $workOrder->serviceCenterUnit;

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No service center unit found',
                ], 404);
            }

            $unit = $this->service->markAsPickedUp($unit, $request->all(), Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Unit marked as picked up',
                'data' => $unit,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark unit as received at service center
     */
    public function markAsReceived(Request $request, int $workOrderId): JsonResponse
    {
        $request->validate([
            'bay_number' => 'nullable|string|max:50',
        ]);

        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $unit = $workOrder->serviceCenterUnit;

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No service center unit found',
                ], 404);
            }

            $unit = $this->service->markAsReceived($unit, $request->all(), Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Unit marked as received',
                'data' => $unit,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark unit as delivered to customer
     */
    public function markAsDelivered(Request $request, int $workOrderId): JsonResponse
    {
        $request->validate([
            'delivery_photos' => 'nullable|array',
            'customer_signature' => 'nullable|string',
        ]);

        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $unit = $workOrder->serviceCenterUnit;

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No service center unit found',
                ], 404);
            }

            $unit = $this->service->markAsDelivered($unit, $request->all(), Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Unit delivered successfully',
                'data' => $unit,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get unit history
     */
    public function history(int $workOrderId): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $unit = $workOrder->serviceCenterUnit;

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No service center unit found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $unit->history()->with('performedByStaff:id,first_name,last_name')->get(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all service center unit statuses
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ServiceCenterUnit::getStatusLabels(),
        ]);
    }
}
