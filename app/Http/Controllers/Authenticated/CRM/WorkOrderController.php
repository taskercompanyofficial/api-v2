<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedBrand;
use App\Models\Customer;
use App\Models\ParentServices;
use App\Models\Status;
use App\Models\WorkOrder;
use App\Models\WorkOrderService;
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
            'customer',
            'address',
            'brand',
            'status',
            'assignedTo',
            'services'
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
            'priority' => 'required|in:low,medium,high,urgent',
            
            // Optional fields
            'brand_complaint_no' => 'nullable|string|max:100',
            // Services
            'services' => 'required|array|min:1',
        ]);

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
                'brand_complaint_no' => $request->brand_complaint_no,
                'indoor_serial_number' => $request->indoor_serial_number,
                'outdoor_serial_number' => $request->outdoor_serial_number,
                'warranty_card_serial' => $request->warranty_card_serial,
                'product_model' => $request->product_model,
                'priority' => $request->priority,
                'customer_description' => $request->customer_description,
                'is_warranty_case' => $request->is_warranty_case ?? false,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Create services
            foreach ($request->services as $serviceData) {
                $parentService = ParentServices::find($serviceData['parent_service_id']);
                
                // Get brand tariff price if available
                $brandTariffPrice = null;
                if ($request->authorized_brand_id) {
                    $brand = AuthorizedBrand::find($request->authorized_brand_id);
                    $tariff = collect($brand->service_charges ?? [])
                        ->firstWhere('service_id', $serviceData['parent_service_id']);
                    $brandTariffPrice = $tariff['paid_price'] ?? null;
                }

                $finalPrice = $brandTariffPrice ?? $parentService->price;

                // For warranty cases, price is 0
                if ($request->is_warranty_case && ($serviceData['service_type'] ?? 'paid') === 'warranty') {
                    $finalPrice = 0;
                }

                WorkOrderService::create([
                    'work_order_id' => $workOrder->id,
                    'category_id' => $parentService->service->category_id,
                    'service_id' => $parentService->service_id,
                    'parent_service_id' => $parentService->id,
                    'service_name' => $parentService->name,
                    'service_type' => $serviceData['service_type'] ?? 'paid',
                    'base_price' => $parentService->price,
                    'brand_tariff_price' => $brandTariffPrice,
                    'final_price' => $finalPrice,
                    'is_warranty_covered' => $request->is_warranty_case && ($serviceData['service_type'] ?? 'paid') === 'warranty',
                ]);
            }

            // Calculate total
            $workOrder->calculateTotal();

            // Set initial status (assuming status ID 1 is "New")
            $newStatus = Status::where('name', 'New')->first();
            if ($newStatus) {
                $workOrder->update(['status_id' => $newStatus->id]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Work order created successfully',
                'data' => $workOrder->load(['customer', 'address', 'services']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create work order',
                'error' => $e->getMessage(),
            ], 500);
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
            'status',
            'subStatus',
            'assignedTo',
            'services.parentService',
            'statusHistory.changedBy',
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

        $workOrder->update($request->only([
            'priority',
            'customer_description',
            'indoor_serial_number',
            'outdoor_serial_number',
            'warranty_card_serial',
            'product_model',
        ]));

        $workOrder->updated_by = auth()->id();
        $workOrder->save();

        return response()->json([
            'success' => true,
            'message' => 'Work order updated successfully',
            'data' => $workOrder,
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
