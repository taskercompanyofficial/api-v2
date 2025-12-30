<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedBrand;
use App\Models\Customer;
use App\Models\ParentServices;
use App\Models\Status;
use App\Models\WorkOrder;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkOrderController extends Controller
{
    /**
     * List work orders with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = WorkOrder::with([
            'customer:id,name,email,phone,whatsapp',
            'address:id,address_line_1,address_line_2,city,state,country,zip_code',
            'brand:id,name',
            'category:id,name',
            'service:id,name',
            'parentService:id,name',
            'product:id,name',
            'status:id,name',
            'subStatus:id,name',
            'assignedTo:id,name',
            'services',
            'branch:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        // Filters
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->has('assigned_to_id')) {
            $query->where('assigned_to_id', $request->assigned_to_id);
        }

        if ($request->has('is_warranty_case')) {
            $query->where('is_warranty_case', $request->is_warranty_case);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'like', "%{$search}%")
                    ->orWhere('brand_complaint_no', 'like', "%{$search}%")
                    ->orWhere('indoor_serial_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $workOrders = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $workOrders,
        ]);
    }

    /**
     * Create new work order
     */
    public function store(Request $request): JsonResponse
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'customer_description' => 'required|string|min:10',
            'authorized_brand_id' => 'required|exists:authorized_brands,id',
            'branch_id' => 'required|exists:our_branches,id',
            'brand_complaint_no' => 'nullable|string|max:100',
            'priority'=> 'required|in:low,medium,high',
            'status_id' => 'nullable|exists:work_order_statuses,id',
            'sub_status_id' => [
                'nullable',
                'exists:work_order_statuses,id',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $request->status_id) {
                        $subStatus = \App\Models\WorkOrderStatus::find($value);
                        
                        // Must be a child status
                        if ($subStatus && is_null($subStatus->parent_id)) {
                            $fail('The selected sub-status must be a child status, not a parent status.');
                        }
                        
                        // Must belong to the selected parent status
                        if ($subStatus && $subStatus->parent_id != $request->status_id) {
                            $fail('The selected sub-status does not belong to the selected status.');
                        }
                    }
                },
            ],
        ]);

        $user = $request->user();
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();
            // Create work order
            $workOrder = WorkOrder::create([
                'work_order_number' => WorkOrder::generateNumber(),
                'customer_id' => $request->customer_id,
                'customer_address_id' => $request->customer_address_id,
                'authorized_brand_id' => $request->authorized_brand_id,
                'branch_id' => $request->branch_id,
                'brand_complaint_no' => $request->brand_complaint_no,
                'priority' => $request->priority,
                'customer_description' => $request->customer_description,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'status' => "success",
                'message' => 'Work order created successfully',
                'data' => $workOrder->load(['customer', 'address', 'services']),
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => "error",
                'code' => 500,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get work order details
     */
    public function show(string $id): JsonResponse
    {
        $workOrder = WorkOrder::with([
            'customer',
            'address',
            'brand',
            'category',
            'service',
            'parentService',
            'product',
            'status',
            'subStatus',
            'assignedTo',
            'services.parentService',
            'files',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $workOrder,
        ]);
    }

    /**
     * Update work order
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($id);

        // Prevent updates if completed or cancelled
        if ($workOrder->completed_at || $workOrder->cancelled_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update completed or cancelled work order',
            ], 403);
        }

        // No validation needed - status updates handled separately
        
        // Update work order with all editable fields
        $workOrder->update($request->only([
            // Work Order Details
            'brand_complaint_no',
            'priority',
            'reject_reason',
            'satisfation_code',
            'without_satisfaction_code_reason',
            // Descriptions
            'customer_description',
            'defect_description',
            'technician_remarks',
            'service_description',
            // Product Information
            'product_indoor_model',
            'product_outdoor_model',
            'indoor_serial_number',
            'outdoor_serial_number',
            'warrenty_serial_number',
            'purchase_date',
            
            // Foreign Keys
            'authorized_brand_id',
            'branch_id',
            'category_id',
            'service_id',
            'parent_service_id',
            'product_id',
        ]));

        $workOrder->updated_by = auth()->id();
        $workOrder->save();

        return response()->json([
            'success' => true,
            'message' => 'Work order updated successfully',
            'data' => $workOrder->load([
                'customer',
                'address',
                'brand',
                'category',
                'service',
                'parentService',
                'product',
                'status',
                'subStatus',
            ]),
        ]);
    }

    /**
     * Delete work order
     */
    public function destroy(string $id): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($id);

        if ($workOrder->completed_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete completed work order',
            ], 403);
        }

        $workOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Work order deleted successfully',
        ]);
    }
}
