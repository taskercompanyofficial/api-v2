<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\QueryFilterTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkOrderController extends Controller
{
    use QueryFilterTrait;

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
            'assignedTo:id,first_name,last_name',
            'services',
            'branch:id,name',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ]);

        // Apply JSON filters from "filters" parameter
        $this->applyJsonFilters($query, $request);

        // Apply sorting
        $this->applySorting($query, $request);

        // Fallback to latest if no sorting specified
        if (!$request->has('sort')) {
            $query->latest();
        }

        $workOrders = $query->paginate(20);

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
            
            // Get the default status: Allocated - Just Launched
            $allocatedStatus = \App\Models\WorkOrderStatus::where('slug', 'allocated')->first();
            $justLaunchedSubStatus = \App\Models\WorkOrderStatus::where('slug', 'just-launched')
                ->where('parent_id', $allocatedStatus?->id)
                ->first();
            
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
                'status_id' => $allocatedStatus?->id,
                'sub_status_id' => $justLaunchedSubStatus?->id,
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
        
        // Get the data and transform empty arrays to null for foreign keys
        $data = $request->only([
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
        ]);

        // Transform empty arrays to null for foreign key fields
        $foreignKeys = ['authorized_brand_id', 'branch_id', 'category_id', 'service_id', 'parent_service_id', 'product_id'];
        foreach ($foreignKeys as $key) {
            if (isset($data[$key]) && (is_array($data[$key]) || $data[$key] === '' || $data[$key] === [])) {
                $data[$key] = null;
            }
        }

        // Update work order with transformed data
        $workOrder->update($data);

        $workOrder->updated_by = auth()->id();
        $workOrder->save();

        return response()->json([
            'status' => "success",
            'message' => 'Work order updated successfully',
        ]);
    }

    /**
     * Schedule work order appointment
     */
    public function schedule(Request $request, string $id): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($id);

        // Prevent scheduling if completed or cancelled
        if ($workOrder->completed_at || $workOrder->cancelled_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot schedule completed or cancelled work order',
            ], 403);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'scheduled_date' => 'required|date|after_or_equal:today',
            'scheduled_time' => 'required|date_format:H:i',
            'remarks' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Combine date and time for validation
        $scheduledDateTime = $request->scheduled_date . ' ' . $request->scheduled_time;
        $scheduledTimestamp = strtotime($scheduledDateTime);
        
        // Check if scheduled time is in the future
        if ($scheduledTimestamp <= time()) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduled date and time must be in the future',
            ], 422);
        }

        // Update appointment date and time
        $workOrder->update([
            'appointment_date' => $request->scheduled_date,
            'appointment_time' => $request->scheduled_time,
        ]);

        // Update status to Dispatched - Technician Accepted if work order has an assigned technician
        if ($workOrder->assigned_to_id) {
            $dispatchedStatus = \App\Models\WorkOrderStatus::where('slug', 'dispatched')->first();
            $technicianAcceptedSubStatus = \App\Models\WorkOrderStatus::where('slug', 'technician-accepted')
                ->where('parent_id', $dispatchedStatus?->id)
                ->first();
            
            $workOrder->status_id = $dispatchedStatus?->id;
            $workOrder->sub_status_id = $technicianAcceptedSubStatus?->id;
        }

        // Optionally add remarks to technician_remarks or a notes field
        if ($request->remarks) {
            $currentRemarks = $workOrder->technician_remarks ?? '';
            $newRemarks = $currentRemarks 
                ? $currentRemarks . "\n\n[Scheduled " . now()->format('Y-m-d H:i') . "]: " . $request->remarks
                : "[Scheduled " . now()->format('Y-m-d H:i') . "]: " . $request->remarks;
            
            $workOrder->technician_remarks = $newRemarks;
        }

        $workOrder->updated_by = auth()->id();
        $workOrder->save();

        return response()->json([
            'success' => true,
            'message' => 'Work order scheduled successfully for ' . date('F j, Y \a\t g:i A', $scheduledTimestamp),
            'data' => [
                'appointment_date' => $workOrder->appointment_date,
                'appointment_time' => $workOrder->appointment_time,
            ],
        ]);
    }

    /**
     * Assign or reassign staff to work order
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($id);

        // Prevent assignment if completed or cancelled
        if ($workOrder->completed_at || $workOrder->cancelled_at) {
            return response()->json([
                'status' => "error",
                'message' => 'Cannot assign staff to completed or cancelled work order',
            ], 403);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'assigned_to_id' => 'required|exists:staff,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => "error",
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $previousAssignedId = $workOrder->assigned_to_id;
        $newAssignedId = $request->assigned_to_id;

        // Check if it's the same staff
        if ($previousAssignedId == $newAssignedId) {
            return response()->json([
                'status' => "error",
                'message' => 'This work order is already assigned to the selected staff member',
            ], 422);
        }

        // Get the Dispatched - Assigned to Technician status
        $dispatchedStatus = \App\Models\WorkOrderStatus::where('slug', 'dispatched')->first();
        $assignedToTechnicianSubStatus = \App\Models\WorkOrderStatus::where('slug', 'assigned-to-technician')
            ->where('parent_id', $dispatchedStatus?->id)
            ->first();

        // Update assignment and status
        $workOrder->update([
            'assigned_to_id' => $newAssignedId,
            'assigned_at' => now(),
            'status_id' => $dispatchedStatus?->id,
            'sub_status_id' => $assignedToTechnicianSubStatus?->id,
        ]);

        // Add notes to technician_remarks if provided
        if ($request->notes) {
            $currentRemarks = $workOrder->technician_remarks ?? '';
            $staff = \App\Models\Staff::find($newAssignedId);
            $action = $previousAssignedId ? 'Reassigned' : 'Assigned';
            
            $newRemarks = $currentRemarks 
                ? $currentRemarks . "\n\n[{$action} " . now()->format('Y-m-d H:i') . " to {$staff->first_name} {$staff->last_name}]: " . $request->notes
                : "[{$action} " . now()->format('Y-m-d H:i') . " to {$staff->first_name} {$staff->last_name}]: " . $request->notes;
            
            $workOrder->technician_remarks = $newRemarks;
        }

        $workOrder->updated_by = auth()->id();
        $workOrder->save();

        // Get staff details for response
        $assignedStaff = \App\Models\Staff::find($newAssignedId);

        // Send WhatsApp notification to assigned staff
        try {
            if ($assignedStaff->phone) {
                $whatsappService = new \App\Services\WhatsAppService();
                $message = $this->formatWorkOrderForWhatsApp($workOrder->fresh([
                    'customer',
                    'address',
                    'brand',
                    'category',
                    'service',
                    'parentService',
                    'product'
                ]));
                
                $whatsappService->sendTextMessage($assignedStaff->phone, $message);
                
                \Illuminate\Support\Facades\Log::info('WhatsApp notification sent to assigned staff', [
                    'work_order_id' => $workOrder->id,
                    'staff_id' => $assignedStaff->id,
                    'phone' => $assignedStaff->phone,
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the assignment
            \Illuminate\Support\Facades\Log::error('Failed to send WhatsApp notification to assigned staff', [
                'work_order_id' => $workOrder->id,
                'staff_id' => $assignedStaff->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => "success",
            'message' => $previousAssignedId 
                ? "Work order reassigned to {$assignedStaff->first_name} {$assignedStaff->last_name} successfully"
                : "Work order assigned to {$assignedStaff->first_name} {$assignedStaff->last_name} successfully",
        ]);
    }

    /**
     * Format work order details for WhatsApp message
     */
    private function formatWorkOrderForWhatsApp(WorkOrder $workOrder): string
    {
        $lines = [];

        // Header
        $lines[] = "═══════════════════════════════════════";
        $lines[] = "*WORK ORDER: {$workOrder->work_order_number}*";
        $lines[] = "*Brand Complaint #: {$workOrder->brand_complaint_no}*";
        $lines[] = "═══════════════════════════════════════";
        $lines[] = "";

        // Customer Information
        $lines[] = "*👤 CUSTOMER INFORMATION*";
        $lines[] = "─────────────────────────────────────";
        $lines[] = "*Name:* " . ($workOrder->customer->name ?? 'N/A');
        if ($workOrder->customer->email) {
            $lines[] = "*Email:* {$workOrder->customer->email}";
        }
        if ($workOrder->customer->phone) {
            $lines[] = "*Phone:* {$workOrder->customer->phone}";
        }
        if ($workOrder->customer->whatsapp) {
            $lines[] = "*WhatsApp:* {$workOrder->customer->whatsapp}";
        }
        $lines[] = "";

        // Address Information
        if ($workOrder->address) {
            $lines[] = "*📍 ADDRESS INFORMATION*";
            $lines[] = "─────────────────────────────────────";
            if ($workOrder->address->address_line_1) {
                $lines[] = "*Address:* {$workOrder->address->address_line_1}";
            }
            if ($workOrder->address->address_line_2) {
                $lines[] = "         {$workOrder->address->address_line_2}";
            }
            $cityStateZip = array_filter([
                $workOrder->address->city,
                $workOrder->address->state,
                $workOrder->address->zip_code
            ]);
            if (!empty($cityStateZip)) {
                $lines[] = "*City:* " . implode(', ', $cityStateZip);
            }
            $lines[] = "";
        }

        // Product Information
        $lines[] = "*🔧 PRODUCT INFORMATION*";
        $lines[] = "─────────────────────────────────────";
        if ($workOrder->brand) {
            $lines[] = "*Brand:* {$workOrder->brand->name}";
        }
        if ($workOrder->product) {
            $lines[] = "*Product:* {$workOrder->product->name}";
        }
        if ($workOrder->product_indoor_model) {
            $lines[] = "*Indoor Model:* {$workOrder->product_indoor_model}";
        }
        if ($workOrder->product_outdoor_model) {
            $lines[] = "*Outdoor Model:* {$workOrder->product_outdoor_model}";
        }
        if ($workOrder->indoor_serial_number) {
            $lines[] = "*Indoor S/N:* {$workOrder->indoor_serial_number}";
        }
        if ($workOrder->outdoor_serial_number) {
            $lines[] = "*Outdoor S/N:* {$workOrder->outdoor_serial_number}";
        }
        $lines[] = "";

        // Service Information
        $lines[] = "*⚙️ SERVICE INFORMATION*";
        $lines[] = "─────────────────────────────────────";
        if ($workOrder->category) {
            $lines[] = "*Category:* {$workOrder->category->name}";
        }
        if ($workOrder->service) {
            $lines[] = "*Service Type:* {$workOrder->service->name}";
        }
        if ($workOrder->parentService) {
            $lines[] = "*Service:* {$workOrder->parentService->name}";
        }
        $lines[] = "";

        // Defect Description
        if ($workOrder->defect_description) {
            $lines[] = "*🔍 DEFECT DESCRIPTION*";
            $lines[] = "─────────────────────────────────────";
            $lines[] = $workOrder->defect_description;
            $lines[] = "";
        }

        // Technician Remarks
        if ($workOrder->technician_remarks) {
            $lines[] = "*💬 TECHNICIAN REMARKS*";
            $lines[] = "─────────────────────────────────────";
            $lines[] = $workOrder->technician_remarks;
            $lines[] = "";
        }

        // Footer
        $lines[] = "═══════════════════════════════════════";
        $lines[] = "Generated: " . now()->format('Y-m-d H:i:s');
        $lines[] = "═══════════════════════════════════════";

        return implode("\n", $lines);
    }

    /**
     * Cancel work order
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($id);

        // Prevent cancellation if already completed or cancelled
        if ($workOrder->completed_at) {
            return response()->json([
                'status' => "error",
                'message' => 'Cannot cancel a completed work order',
            ], 403);
        }

        if ($workOrder->cancelled_at) {
            return response()->json([
                'status' => "error",
                'message' => 'Work order is already cancelled',
            ], 403);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => "error",
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get the Cancelled status
        $cancelledStatus = \App\Models\WorkOrderStatus::where('slug', 'cancelled')->first();
        $customerCancelledSubStatus = \App\Models\WorkOrderStatus::where('slug', 'customer-cancelled')
            ->where('parent_id', $cancelledStatus?->id)
            ->first();

        // Update work order
        $workOrder->update([
            'status_id' => $cancelledStatus?->id,
            'sub_status_id' => $customerCancelledSubStatus?->id,
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'reject_reason' => $request->cancellation_reason, // Store cancellation reason
        ]);

        $workOrder->updated_by = auth()->id();
        $workOrder->save();

        // Get staff details for logging
        $staff = \App\Models\Staff::find(auth()->id());

        \Illuminate\Support\Facades\Log::info('Work order cancelled', [
            'work_order_id' => $workOrder->id,
            'work_order_number' => $workOrder->work_order_number,
            'cancelled_by' => $staff?->first_name . ' ' . $staff?->last_name,
            'reason' => $request->cancellation_reason,
        ]);

        return response()->json([
            'status' => "success",
            'message' => "Work order #{$workOrder->work_order_number} has been cancelled successfully",
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
