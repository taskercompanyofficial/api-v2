<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
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
        $workOrder = WorkOrder::findOrFail($workOrderId);
        $parts = $workOrder->workOrderParts()
            ->with(['part', 'creator', 'updater'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $parts
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $workOrderId)
    {
        $request->validate([
            'part_id' => 'required|exists:parts,id',
            'quantity' => 'required|integer|min:1',
            'request_type' => 'required|in:warranty,payable',
            'pricing_source' => 'required_if:request_type,payable|in:none,local,brand',
            'unit_price' => 'nullable|numeric|min:0',
            'part_request_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $part = WorkOrderPart::create([
                'work_order_id' => $workOrder->id,
                'part_id' => $request->part_id,
                'quantity' => $request->quantity,
                'request_type' => $request->request_type,
                'pricing_source' => $request->request_type === 'warranty' ? 'none' : $request->pricing_source,
                'unit_price' => $request->request_type === 'warranty' ? 0 : ($request->unit_price ?? 0),
                'part_request_number' => $request->part_request_number,
                'notes' => $request->notes,
                'status' => 'requested',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Part demanded successfully',
                'data' => $part->load(['part', 'creator'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to demand part: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $workOrderId, $workOrderPartId)
    {
        $request->validate([
            'quantity' => 'sometimes|integer|min:1',
            'request_type' => 'sometimes|in:warranty,payable',
            'pricing_source' => 'sometimes|in:none,local,brand',
            'unit_price' => 'sometimes|numeric|min:0',
            'part_request_number' => 'nullable|string|max:100',
            'status' => 'sometimes|in:requested,dispatched,received,installed,returned,cancelled',
            'is_returned_faulty' => 'sometimes|boolean',
            'notes' => 'nullable|string',
            'payment_proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'gas_pass_slip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'return_slip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        try {
            $workOrderPart = WorkOrderPart::where('work_order_id', $workOrderId)
                ->where('id', $workOrderPartId)
                ->firstOrFail();

            // Workflow validation: Payable parts require payment proof before dispatch
            if ($request->has('status') && $request->status === 'dispatched') {
                if ($workOrderPart->request_type === 'payable' && !$workOrderPart->payment_proof_path && !$request->hasFile('payment_proof')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Payment proof is required before dispatching payable parts'
                    ], 422);
                }
            }

            $updateData = $request->only([
                'quantity', 'request_type', 'pricing_source', 'unit_price',
                'part_request_number', 'status', 'is_returned_faulty', 'notes'
            ]);

            // Handle document uploads
            $workOrder = WorkOrder::findOrFail($workOrderId);
            
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $fileName = 'payment_proof_' . $workOrderPartId . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs(
                    'work-orders/' . $workOrder->work_order_number . '/parts',
                    $fileName,
                    'public'
                );
                $updateData['payment_proof_path'] = $path;
                $updateData['payment_proof_uploaded_at'] = now();
            }

            if ($request->hasFile('gas_pass_slip')) {
                $file = $request->file('gas_pass_slip');
                $fileName = 'gas_pass_' . $workOrderPartId . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs(
                    'work-orders/' . $workOrder->work_order_number . '/parts',
                    $fileName,
                    'public'
                );
                $updateData['gas_pass_slip_path'] = $path;
                $updateData['gas_pass_slip_uploaded_at'] = now();
            }

            if ($request->hasFile('return_slip')) {
                $file = $request->file('return_slip');
                $fileName = 'return_slip_' . $workOrderPartId . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs(
                    'work-orders/' . $workOrder->work_order_number . '/parts',
                    $fileName,
                    'public'
                );
                $updateData['return_slip_path'] = $path;
                $updateData['return_slip_uploaded_at'] = now();
            }

            if ($request->has('is_returned_faulty') && $request->is_returned_faulty && !$workOrderPart->is_returned_faulty) {
                $updateData['faulty_part_returned_at'] = now();
            }

            $workOrderPart->update($updateData);

            return response()->json([
                'status' => 'success',
                'message' => 'Part demand updated successfully',
                'data' => $workOrderPart->load(['part', 'creator', 'updater'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update part demand: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($workOrderId, $workOrderPartId)
    {
        try {
            $workOrderPart = WorkOrderPart::where('work_order_id', $workOrderId)
                ->where('id', $workOrderPartId)
                ->firstOrFail();

            if ($workOrderPart->status !== 'requested') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete a part demand that is already processed'
                ], 422);
            }

            $workOrderPart->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Part demand removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete part demand: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update status for multiple parts.
     */
    public function bulkUpdateStatus(Request $request, $workOrderId)
    {
        $request->validate([
            'part_ids' => 'required|array',
            'part_ids.*' => 'exists:work_order_parts,id',
            'status' => 'required|in:requested,dispatched,received,installed,returned,cancelled',
        ]);

        try {
            WorkOrderPart::whereIn('id', $request->part_ids)
                ->where('work_order_id', $workOrderId)
                ->update([
                    'status' => $request->status,
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Parts status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update parts status: ' . $e->getMessage()
            ], 500);
        }
    }

   
}
