<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartRequest;
use App\Models\StoreItemInstance;
use App\Models\WorkOrder;
use App\Models\WorkOrderHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderPartController extends Controller
{
    /**
     * Display a listing of parts for a specific work order.
     */
    public function index($workOrderId)
    {
        $workOrder = WorkOrder::with(['partRequests.part', 'partRequests.storeItem', 'partRequests.instance', 'partRequests.creator'])->findOrFail($workOrderId);

        return response()->json([
            'status' => 'success',
            'data' => $workOrder->partRequests
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $workOrderId)
    {
        $request->validate([
            'part_id' => 'nullable|exists:parts,id',
            'store_item_id' => 'nullable|exists:store_items,id',
            'quantity' => 'required|integer|min:1',
            'request_type' => 'required|in:warranty,payable',
            'unit_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if (!$request->part_id && !$request->store_item_id) {
            return response()->json(['status' => 'error', 'message' => 'Either part_id or store_item_id is required'], 422);
        }

        try {
            return DB::transaction(function () use ($request, $workOrderId) {
                $workOrder = WorkOrder::findOrFail($workOrderId);

                $data = [
                    'work_order_id' => $workOrder->id,
                    'part_id' => $request->part_id,
                    'store_item_id' => $request->store_item_id,
                    'quantity' => $request->quantity,
                    'request_type' => $request->request_type,
                    'unit_price' => $request->unit_price ?? 0,
                    'notes' => $request->notes,
                    'status' => 'requested',
                ];

                // Automatic Reservation Logic
                if ($request->store_item_id) {
                    $availableInstance = StoreItemInstance::where('store_item_id', $request->store_item_id)
                        ->where('status', 'active')
                        ->first();

                    if ($availableInstance) {
                        $data['store_item_instance_id'] = $availableInstance->id;
                        $data['status'] = 'reserved';

                        // Mark instance as reserved
                        $availableInstance->update([
                            'status' => 'reserved',
                            'complaint_number' => $workOrder->id // Store work order ID for traceability
                        ]);
                    }
                }

                $partRequest = PartRequest::create($data);

                // Log History
                $itemName = $partRequest->storeItem?->name ?? $partRequest->part?->name ?? 'Unknown Part';
                $statusMsg = $partRequest->status === 'reserved' ? "and automatically reserved instance #{$partRequest->store_item_instance_id}" : "";

                WorkOrderHistory::log(
                    workOrderId: $workOrderId,
                    actionType: 'created',
                    description: "Requested part '{$itemName}' {$statusMsg}",
                    metadata: [
                        'part_request_id' => $partRequest->id,
                        'item_name' => $itemName,
                        'status' => $partRequest->status,
                        'type' => 'part_request_created'
                    ]
                );

                return response()->json([
                    'status' => 'success',
                    'message' => 'Part request created successfully',
                    'data' => $partRequest->load(['part', 'storeItem', 'instance', 'creator'])
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to request part: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $workOrderId, $id)
    {
        $request->validate([
            'status' => 'sometimes|in:requested,reserved,dispatched,received,used,returned,cancelled',
            'notes' => 'nullable|string',
            'is_returned_faulty' => 'sometimes|boolean',
        ]);

        try {
            return DB::transaction(function () use ($request, $workOrderId, $id) {
                $partRequest = PartRequest::where('work_order_id', $workOrderId)->findOrFail($id);
                $oldStatus = $partRequest->status;

                $data = $request->only(['status', 'notes']);

                // Handle status transitions
                if ($request->has('status') && $request->status !== $oldStatus) {
                    // Logic for returning instances if cancelled or returned
                    if (in_array($request->status, ['returned', 'cancelled']) && $partRequest->store_item_instance_id) {
                        StoreItemInstance::where('id', $partRequest->store_item_instance_id)->update([
                            'status' => 'active',
                            'complaint_number' => null
                        ]);
                    }

                    // Logic for usage
                    if ($request->status === 'used' && $partRequest->store_item_instance_id) {
                        StoreItemInstance::where('id', $partRequest->store_item_instance_id)->update([
                            'status' => 'used',
                            'used_date' => now(),
                            'used_price' => $partRequest->unit_price
                        ]);
                    }
                }

                $partRequest->update($data);

                if ($request->has('status')) {
                    WorkOrderHistory::log(
                        workOrderId: $workOrderId,
                        actionType: 'updated',
                        description: "Updated part request status to '{$request->status}'",
                        metadata: [
                            'part_request_id' => $partRequest->id,
                            'old_status' => $oldStatus,
                            'new_status' => $request->status,
                            'type' => 'part_request_update'
                        ]
                    );
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Part request updated successfully',
                    'data' => $partRequest->load(['part', 'storeItem', 'instance', 'creator', 'updater'])
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update part request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($workOrderId, $id)
    {
        try {
            return DB::transaction(function () use ($workOrderId, $id) {
                $partRequest = PartRequest::where('work_order_id', $workOrderId)->findOrFail($id);

                if ($partRequest->status === 'used') {
                    return response()->json(['status' => 'error', 'message' => 'Cannot delete used part requests'], 422);
                }

                // Release instance if reserved/received
                if ($partRequest->store_item_instance_id) {
                    StoreItemInstance::where('id', $partRequest->store_item_instance_id)->update([
                        'status' => 'active',
                        'complaint_number' => null
                    ]);
                }

                $partRequest->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Part request removed successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete part request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update status for multiple parts.
     */
    public function bulkUpdateStatus(Request $request, $workOrderId)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:part_requests,id',
            'status' => 'required|in:requested,reserved,dispatched,received,used,returned,cancelled',
        ]);

        try {
            DB::transaction(function () use ($request, $workOrderId) {
                foreach ($request->ids as $id) {
                    $this->update(new Request(['status' => $request->status]), $workOrderId, $id);
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Part requests bulk updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to bulk update part requests: ' . $e->getMessage()
            ], 500);
        }
    }
}
