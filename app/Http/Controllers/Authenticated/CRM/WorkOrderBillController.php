<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderBill;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class WorkOrderBillController extends Controller
{
    /**
     * Get all bills across all work orders (for the Bills page)
     */
    public function allBills(Request $request): JsonResponse
    {
        $query = WorkOrderBill::with([
            'workOrder:id,work_order_number,customer_id',
            'workOrder.customer:id,name,phone',
            'createdBy:id,first_name,last_name',
        ]);

        // Search by reference or work order number
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('workOrder', function ($wq) use ($search) {
                        $wq->where('work_order_number', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by document type
        if ($type = $request->input('document_type')) {
            $query->where('document_type', $type);
        }

        // Filter by date range
        if ($from = $request->input('from_date')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->input('to_date')) {
            $query->whereDate('date', '<=', $to);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = $request->input('per_page', 20);
        $bills = $query->paginate($perPage);

        return response()->json([
            'status' => 200,
            'message' => 'Bills retrieved successfully',
            'data' => $bills,
        ]);
    }

    /**
     * Get a single bill globally (without knowing work order context)
     */
    public function globalShow(int $id): JsonResponse
    {
        $bill = WorkOrderBill::with([
            'workOrder:id,work_order_number',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ])->findOrFail($id);

        return response()->json([
            'status' => 200,
            'message' => 'Bill retrieved successfully',
            'data' => $bill,
        ]);
    }

    /**
     * Get all bills for a work order
     */
    public function index(int $workOrderId): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);

        $bills = $workOrder->bills()
            ->with(['createdBy:id,first_name,last_name', 'updatedBy:id,first_name,last_name'])
            ->get();

        return response()->json([
            'status' => 200,
            'message' => 'Bills retrieved successfully',
            'data' => $bills,
        ]);
    }

    /**
     * Store a new bill
     */
    public function store(Request $request, int $workOrderId): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);

        $validator = Validator::make($request->all(), [
            'document_type' => 'required|in:invoice,quotation',
            'reference' => 'required|string|unique:work_order_bills,reference',
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'data' => 'required|array',
            'subtotal' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payable_amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'balance_due' => 'nullable|numeric',
            'status' => 'nullable|in:draft,sent,paid,overdue,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bill = $workOrder->bills()->create([
            'document_type' => $request->document_type,
            'reference' => $request->reference,
            'date' => $request->date,
            'due_date' => $request->due_date,
            'data' => $request->data,
            'subtotal' => $request->subtotal ?? 0,
            'tax_rate' => $request->tax_rate ?? 0,
            'tax_amount' => $request->tax_amount ?? 0,
            'discount_type' => $request->discount_type ?? 'percentage',
            'discount_value' => $request->discount_value ?? 0,
            'discount_amount' => $request->discount_amount ?? 0,
            'payable_amount' => $request->payable_amount ?? 0,
            'paid_amount' => $request->paid_amount ?? 0,
            'balance_due' => $request->balance_due ?? $request->payable_amount,
            'status' => $request->status ?? 'draft',
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Bill created successfully',
            'data' => $bill->load(['createdBy:id,first_name,last_name']),
        ], 201);
    }

    /**
     * Get a single bill
     */
    public function show(int $workOrderId, int $id): JsonResponse
    {
        $bill = WorkOrderBill::where('work_order_id', $workOrderId)->with([
            'workOrder:id,work_order_number',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ])->findOrFail($id);

        return response()->json([
            'status' => 200,
            'message' => 'Bill retrieved successfully',
            'data' => $bill,
        ]);
    }

    /**
     * Update a bill
     */
    public function update(Request $request, int $workOrderId, int $id): JsonResponse
    {
        $bill = WorkOrderBill::where('work_order_id', $workOrderId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'document_type' => 'sometimes|in:invoice,quotation',
            'reference' => 'sometimes|string|unique:work_order_bills,reference,' . $id,
            'date' => 'sometimes|date',
            'due_date' => 'nullable|date',
            'data' => 'sometimes|array',
            'subtotal' => 'sometimes|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payable_amount' => 'sometimes|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'balance_due' => 'nullable|numeric',
            'status' => 'nullable|in:draft,sent,paid,overdue,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bill->update($request->only([
            'document_type',
            'reference',
            'date',
            'due_date',
            'data',
            'subtotal',
            'tax_rate',
            'tax_amount',
            'discount_type',
            'discount_value',
            'discount_amount',
            'payable_amount',
            'paid_amount',
            'balance_due',
            'status',
        ]));

        return response()->json([
            'status' => 200,
            'message' => 'Bill updated successfully',
            'data' => $bill->fresh()->load(['createdBy:id,first_name,last_name', 'updatedBy:id,first_name,last_name']),
        ]);
    }

    /**
     * Delete a bill
     */
    public function destroy(int $workOrderId, int $id): JsonResponse
    {
        $bill = WorkOrderBill::where('work_order_id', $workOrderId)->findOrFail($id);
        $bill->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Bill deleted successfully',
        ]);
    }

    /**
     * Quick status update
     */
    public function updateStatus(Request $request, int $workOrderId, int $id): JsonResponse
    {
        $bill = WorkOrderBill::where('work_order_id', $workOrderId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bill->update(['status' => $request->status]);

        return response()->json([
            'status' => 200,
            'message' => 'Bill status updated successfully',
            'data' => $bill->fresh(),
        ]);
    }
}
