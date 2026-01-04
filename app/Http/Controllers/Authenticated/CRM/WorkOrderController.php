<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderFile;
use App\Models\WorkOrderHistory;
use App\Models\WorkOrderStatus;
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
        $page = $request->page ?? 1;
        $perPage = $request->perPage ?? 20;
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

        // Global search across multiple fields
        if ($request->has('work_order_number') && $request->work_order_number) {
            $searchTerm = $request->work_order_number;
            
            $query->where(function ($q) use ($searchTerm) {
                // Search in work order fields
                $q->where('work_order_number', 'like', "%{$searchTerm}%")
                  ->orWhere('brand_complaint_no', 'like', "%{$searchTerm}%")
                  ->orWhere('indoor_serial_number', 'like', "%{$searchTerm}%")
                  ->orWhere('outdoor_serial_number', 'like', "%{$searchTerm}%")
                  ->orWhere('product_indoor_model', 'like', "%{$searchTerm}%")
                  ->orWhere('product_outdoor_model', 'like', "%{$searchTerm}%")
                  // Search in customer relationship
                  ->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                      $customerQuery->where('name', 'like', "%{$searchTerm}%")
                                    ->orWhere('phone', 'like', "%{$searchTerm}%")
                                    ->orWhere('whatsapp', 'like', "%{$searchTerm}%");
                  });
            });
        }

        // Apply JSON filters from "filters" parameter
        $this->applyJsonFilters($query, $request);

        // Apply sorting
        $this->applySorting($query, $request);

        // Fallback to latest if no sorting specified
        if (!$request->has('sort')) {
            $query->latest();
        }

        $workOrders = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $workOrders->items(),
            'pagination' => [
                'total' => $workOrders->total(),
                'per_page' => $workOrders->perPage(),
                'current_page' => $workOrders->currentPage(),
                'last_page' => $workOrders->lastPage(),
                'from' => $workOrders->firstItem(),
                'to' => $workOrders->lastItem(),
            ],
        ]);
    }

    /**
     * Create new work order
     */
    public function store(Request $request): JsonResponse
    {
        // Validation
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'category_id'=> 'required|exists:categories,id',
            'service_id'=> 'required|exists:services,id',
            'parent_service_id'=> 'exists:parent_services,id',
            'product_id'=> 'required|exists:products,id',
            'customer_description' => 'required|string|min:10',
            'authorized_brand_id' => 'required|exists:authorized_brands,id',
            'brand_complaint_no' => 'nullable|string|max:100',
            'priority'=> 'required|in:low,medium,high',
           
        ]);

        $user = $request->user();
       
        try {
            DB::beginTransaction();
            
            // Get the default status: Allocated - Just Launched
            $allocatedStatus = WorkOrderStatus::where('slug', 'allocated')->first();
            $justLaunchedSubStatus = WorkOrderStatus::where('slug', 'just-launched')
                ->where('parent_id', $allocatedStatus?->id)
                ->first();
            
            // Create work order
            $workOrder = WorkOrder::create([
                'work_order_number' => WorkOrder::generateNumber(),
                'customer_id' => $request->customer_id,
                'customer_address_id' => $request->customer_address_id,
                'category_id' => $request->category_id,
                'service_id' => $request->service_id,
                'parent_service_id' => $request->parent_service_id,
                'product_id' => $request->product_id,
                'authorized_brand_id' => $request->authorized_brand_id,
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
            'warrenty_status',
            'warrenty_end_date',
            
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

        // Log scheduling history
        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'scheduled',
            description: "Work order scheduled for {$workOrder->appointment_date} at {$workOrder->appointment_time}",
            metadata: [
                'appointment_date' => $workOrder->appointment_date,
                'appointment_time' => $workOrder->appointment_time,
                'remarks' => $request->remarks,
            ]
        );

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
        if ($workOrder->completed_at || $workOrder->cancelled_at || $workOrder->rejected_at || $workOrder->closed_at) {
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
        $dispatchedStatus = WorkOrderStatus::where('slug', 'dispatched')->first();
        $assignedToTechnicianSubStatus = WorkOrderStatus::where('slug', 'assigned-to-technician')
            ->where('parent_id', $dispatchedStatus?->id)
            ->first();

        // Prepare update data
        $updateData = [
            'assigned_to_id' => $newAssignedId,
            'assigned_at' => now(),
            'status_id' => $dispatchedStatus?->id,
            'sub_status_id' => $assignedToTechnicianSubStatus?->id,
        ];

        // If reassigning (not first assignment), reset all action timestamps
        if ($previousAssignedId) {
            $updateData['accepted_at'] = null;
            $updateData['rejected_at'] = null;
            $updateData['rejected_by'] = null;
            $updateData['reject_reason'] = null;
            
            // Reset service timing
            $updateData['appointment_date'] = null;
            $updateData['appointment_time'] = null;
            $updateData['service_start_date'] = null;
            $updateData['service_start_time'] = null;
            $updateData['service_end_date'] = null;
            $updateData['service_end_time'] = null;
        }

        // Update assignment and status
        $workOrder->update($updateData);

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
        $previousStaff = $previousAssignedId ? \App\Models\Staff::find($previousAssignedId) : null;

        // Log assignment history
        $action = $previousAssignedId ? 'reassigned' : 'assigned';
        $description = $previousAssignedId 
            ? "Work order reassigned from {$previousStaff->first_name} {$previousStaff->last_name} to {$assignedStaff->first_name} {$assignedStaff->last_name}"
            : "Work order assigned to {$assignedStaff->first_name} {$assignedStaff->last_name}";

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: $action,
            description: $description,
            fieldName: 'assigned_to_id',
            oldValue: $previousStaff ? "{$previousStaff->first_name} {$previousStaff->last_name}" : null,
            newValue: "{$assignedStaff->first_name} {$assignedStaff->last_name}",
            metadata: [
                'previous_staff_id' => $previousAssignedId,
                'new_staff_id' => $newAssignedId,
                'notes' => $request->notes,
                'status_changed_to' => $dispatchedStatus?->name,
                'sub_status_changed_to' => $assignedToTechnicianSubStatus?->name,
            ]
        );


        // Send WhatsApp notification to assigned staff
        try {
            // Send Push Notification
            if ($assignedStaff->device_token) {
                $this->sendPushNotification(
                    $assignedStaff->device_token,
                    "New Work Order Assigned",
                    "You have been assigned logic for Work Order #{$workOrder->work_order_number}",
                    ['work_order_id' => $workOrder->id]
                );
            }

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
        } catch (Exception $e) {
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
        $cancelledStatus = WorkOrderStatus::where('slug', 'cancelled')->first();
        $customerCancelledSubStatus = WorkOrderStatus::where('slug', 'customer-cancelled')
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

        // Log cancellation history
        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'cancelled',
            description: "Work order cancelled by {$staff->first_name} {$staff->last_name}. Reason: {$request->cancellation_reason}",
            metadata: [
                'cancellation_reason' => $request->cancellation_reason,
                'cancelled_by_staff_id' => auth()->id(),
                'cancelled_by_staff_name' => "{$staff->first_name} {$staff->last_name}",
                'status_changed_to' => $cancelledStatus?->name,
                'sub_status_changed_to' => $customerCancelledSubStatus?->name,
            ]
        );

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

    /**
     * Get work order history
     */
    public function history(string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            
            $histories = $workOrder->histories()
                ->with('user:id,name,email')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $histories,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve work order history.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send reminder notification to assigned staff
     */
    public function sendReminder(Request $request, string $id): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'remark' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ]);
            }

            $workOrder = WorkOrder::with(['assignedTo', 'customer'])->findOrFail($id);

            // Check if work order has assigned staff
            if (!$workOrder->assigned_to_id || !$workOrder->assignedTo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This work order is not assigned to any staff member.',
                ]);
            }

            // Check if assigned staff has device token
            if (!$workOrder->assignedTo->device_token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Assigned staff does not have push notifications enabled.',
                ]);
            }

            // Prepare reminder data
            $reminderData = [
                'sent_at' => now()->toDateTimeString(),
                'sent_by' => auth()->id(),
                'sent_by_name' => auth()->user()->name,
                'remark' => $request->remark,
                'staff_id' => $workOrder->assignedTo->id,
                'staff_name' => $workOrder->assignedTo->first_name . ' ' . $workOrder->assignedTo->last_name,
            ];

            // Get existing reminders or initialize empty array
            $reminders = $workOrder->reminders ? json_decode($workOrder->reminders, true) : [];
            
            // Add new reminder to array
            $reminders[] = $reminderData;

            // Update work order with new reminders array
            $workOrder->update([
                'reminders' => json_encode($reminders),
            ]);

            // Send push notification with custom remark
            $notificationSent = $this->sendPushNotification(
                $workOrder->assignedTo->device_token,
                'Work Order Reminder',
                $request->remark,
                [
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'type' => 'reminder',
                ]
            );

            // Log reminder in history
            WorkOrderHistory::log(
                workOrderId: $workOrder->id,
                actionType: 'reminder_sent',
                description: "Reminder sent: {$request->remark}",
                metadata: [
                    'staff_id' => $workOrder->assignedTo->id,
                    'staff_name' => $workOrder->assignedTo->first_name . ' ' . $workOrder->assignedTo->last_name,
                    'remark' => $request->remark,
                    'notification_sent' => $notificationSent,
                    'reminder_count' => count($reminders),
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => "Reminder sent to {$workOrder->assignedTo->first_name} {$workOrder->assignedTo->last_name}",
                'data' => [
                    'reminder_count' => count($reminders),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send reminder: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Send Push Notification using Expo
     */
    private function sendPushNotification($to, $title, $body, $data = [])
    {
        $url = 'https://exp.host/--/api/v2/push/send';
        $postData = [
            'to' => $to,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }

    /**
     * Duplicate work order with selective copying
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'copy_product_details' => 'boolean',
                'copy_service_details' => 'boolean',
                'copy_attachments' => 'boolean',
                'quantity' => 'integer|min:1|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ]);
            }

            // Load original work order
            $originalWorkOrder = WorkOrder::with(['files'])->findOrFail($id);

            // Get quantity (default to 1 if not provided)
            $quantity = $request->input('quantity', 1);

            // Arrays to store created work orders
            $createdWorkOrders = [];
            $createdIds = [];
            $createdNumbers = [];

            // Create work orders based on quantity
            for ($i = 0; $i < $quantity; $i++) {
                // Create new work order data
                $newWorkOrderData = [
                    // Always copy customer and address
                    'customer_id' => $originalWorkOrder->customer_id,
                    'customer_address_id' => $originalWorkOrder->customer_address_id,
                    
                    // Generate new work order number
                    'work_order_number' => WorkOrder::generateNumber(),
                    
                    // Copy basic fields
                    'work_order_source' => $originalWorkOrder->work_order_source,
                    'priority' => $originalWorkOrder->priority,
                    'customer_description' => $originalWorkOrder->customer_description,
                    'defect_description' => $originalWorkOrder->defect_description,
                    
                    // Set initial status (not assigned/dispatched)
                    'status_id' => null,
                    'sub_status_id' => null,
                    
                    // Audit
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ];

                // Conditionally copy service details
                if ($request->copy_service_details) {
                    $newWorkOrderData['authorized_brand_id'] = $originalWorkOrder->authorized_brand_id;
                    $newWorkOrderData['branch_id'] = $originalWorkOrder->branch_id;
                    $newWorkOrderData['category_id'] = $originalWorkOrder->category_id;
                    $newWorkOrderData['service_id'] = $originalWorkOrder->service_id;
                    $newWorkOrderData['parent_service_id'] = $originalWorkOrder->parent_service_id;
                }

                // Conditionally copy product details
                if ($request->copy_product_details) {
                    $newWorkOrderData['product_id'] = $originalWorkOrder->product_id;
                    $newWorkOrderData['product_indoor_model'] = $originalWorkOrder->product_indoor_model;
                    $newWorkOrderData['product_outdoor_model'] = $originalWorkOrder->product_outdoor_model;
                    $newWorkOrderData['indoor_serial_number'] = $originalWorkOrder->indoor_serial_number;
                    $newWorkOrderData['outdoor_serial_number'] = $originalWorkOrder->outdoor_serial_number;
                    $newWorkOrderData['warrenty_serial_number'] = $originalWorkOrder->warrenty_serial_number;
                    $newWorkOrderData['purchase_date'] = $originalWorkOrder->purchase_date;
                    $newWorkOrderData['warrenty_status'] = $originalWorkOrder->warrenty_status;
                }

                // Create new work order
                $newWorkOrder = WorkOrder::create($newWorkOrderData);
                $createdWorkOrders[] = $newWorkOrder;
                $createdIds[] = $newWorkOrder->id;
                $createdNumbers[] = $newWorkOrder->work_order_number;

                // Conditionally copy attachments
                if ($request->copy_attachments) {
                    // Reload files to ensure we have the collection
                    $files = WorkOrderFile::where('work_order_id', $originalWorkOrder->id)->get();
                    
                    if ($files->isNotEmpty()) {
                        foreach ($files as $file) {
                            WorkOrderFile::create([
                                'work_order_id' => $newWorkOrder->id,
                                'file_type_id' => $file->file_type_id,
                                'file_path' => $file->file_path,
                                'file_name' => $file->file_name,
                                'file_size' => $file->file_size,
                                'mime_type' => $file->mime_type,
                                'uploaded_by' => auth()->id(),
                            ]);
                        }
                    }
                }

                // Log duplication in history
                WorkOrderHistory::log(
                    workOrderId: $newWorkOrder->id,
                    actionType: 'created',
                    description: "Work order created as duplicate of #{$originalWorkOrder->work_order_number}" . ($quantity > 1 ? " (copy " . ($i + 1) . " of {$quantity})" : ""),
                    metadata: [
                        'original_work_order_id' => $originalWorkOrder->id,
                        'original_work_order_number' => $originalWorkOrder->work_order_number,
                        'copied_product_details' => $request->copy_product_details ?? false,
                        'copied_service_details' => $request->copy_service_details ?? false,
                        'copied_attachments' => $request->copy_attachments ?? false,
                        'copy_number' => $i + 1,
                        'total_copies' => $quantity,
                    ]
                );
            }

            // Log in original work order
            $duplicateDescription = $quantity > 1 
                ? "Work order duplicated {$quantity} times: " . implode(', ', array_map(fn($num) => "#{$num}", $createdNumbers))
                : "Work order duplicated to #{$createdNumbers[0]}";

            WorkOrderHistory::log(
                workOrderId: $originalWorkOrder->id,
                actionType: 'duplicated',
                description: $duplicateDescription,
                metadata: [
                    'new_work_order_ids' => $createdIds,
                    'new_work_order_numbers' => $createdNumbers,
                    'quantity' => $quantity,
                ]
            );

            // Return response based on quantity
            if ($quantity === 1) {
                return response()->json([
                    'status' => "success",
                    'message' => "Work order duplicated successfully as #{$createdNumbers[0]}",
                    'data' => [
                        'id' => $createdIds[0],
                        'work_order_number' => $createdNumbers[0],
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => "success",
                    'message' => "Successfully created {$quantity} work orders",
                    'data' => [
                        'ids' => $createdIds,
                        'work_order_numbers' => $createdNumbers,
                        'quantity' => $quantity,
                    ]
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => "error",
                'message' => 'Failed to duplicate work order .'.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reopen a work order (create new work order with incremental suffix)
     */
    public function reopen(Request $request, string $id): JsonResponse
    {
        try {
            $originalWorkOrder = WorkOrder::findOrFail($id);

            // Get the base work order number (without any suffix)
            $baseWorkOrderNumber = $originalWorkOrder->work_order_number;
            
            // Remove existing suffix if present (e.g., "WO-001-1" becomes "WO-001")
            $baseWorkOrderNumber = preg_replace('/-\d+$/', '', $baseWorkOrderNumber);

            // Find all work orders with this base number
            $existingReopened = WorkOrder::where('work_order_number', 'like', $baseWorkOrderNumber . '-%')
                ->orderBy('work_order_number', 'desc')
                ->first();

            // Determine the next suffix number
            $nextSuffix = 1;
            if ($existingReopened) {
                // Extract the suffix from the last reopened work order
                if (preg_match('/-(\d+)$/', $existingReopened->work_order_number, $matches)) {
                    $nextSuffix = (int)$matches[1] + 1;
                }
            }

            // Generate new work order number with suffix
            $newWorkOrderNumber = $baseWorkOrderNumber . '-' . $nextSuffix;

            // Create new work order with copied data
            $newWorkOrder = WorkOrder::create([
                'work_order_number' => $newWorkOrderNumber,
                'customer_id' => $originalWorkOrder->customer_id,
                'customer_address_id' => $originalWorkOrder->customer_address_id,
                'authorized_brand_id' => $originalWorkOrder->authorized_brand_id,
                'branch_id' => $originalWorkOrder->branch_id,
                'category_id' => $originalWorkOrder->category_id,
                'service_id' => $originalWorkOrder->service_id,
                'parent_service_id' => $originalWorkOrder->parent_service_id,
                'product_id' => $originalWorkOrder->product_id,
                
                // Copy product details
                'product_indoor_model' => $originalWorkOrder->product_indoor_model,
                'product_outdoor_model' => $originalWorkOrder->product_outdoor_model,
                'indoor_serial_number' => $originalWorkOrder->indoor_serial_number,
                'outdoor_serial_number' => $originalWorkOrder->outdoor_serial_number,
                'warrenty_serial_number' => $originalWorkOrder->warrenty_serial_number,
                'warrenty_status' => $originalWorkOrder->warrenty_status,
                'warrenty_end_date' => $originalWorkOrder->warrenty_end_date,
                
                // Copy descriptions
                'customer_description' => $originalWorkOrder->customer_description,
                'defect_description' => $originalWorkOrder->defect_description,
                
                // Set initial status
                'status_id' => WorkOrderStatus::where('name', 'Pending')->first()?->id,
                
                // Set priority
                'priority' => $request->priority ?? $originalWorkOrder->priority ?? 'medium',
                
                // Audit fields
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Log reopening in history for new work order
            WorkOrderHistory::log(
                workOrderId: $newWorkOrder->id,
                actionType: 'created',
                description: "Work order reopened from #{$originalWorkOrder->work_order_number}",
                metadata: [
                    'original_work_order_id' => $originalWorkOrder->id,
                    'original_work_order_number' => $originalWorkOrder->work_order_number,
                    'reopen_reason' => $request->reopen_reason ?? 'Complaint reopened',
                    'reopen_count' => $nextSuffix,
                ]
            );

            // Log in original work order
            WorkOrderHistory::log(
                workOrderId: $originalWorkOrder->id,
                actionType: 'reopened',
                description: "Work order reopened as #{$newWorkOrder->work_order_number}",
                metadata: [
                    'new_work_order_id' => $newWorkOrder->id,
                    'new_work_order_number' => $newWorkOrder->work_order_number,
                    'reopen_reason' => $request->reopen_reason ?? 'Complaint reopened',
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => "Work order reopened successfully as #{$newWorkOrder->work_order_number}",
                'data' => [
                    'id' => $newWorkOrder->id,
                    'work_order_number' => $newWorkOrder->work_order_number,
                    'original_work_order_id' => $originalWorkOrder->id,
                    'original_work_order_number' => $originalWorkOrder->work_order_number,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reopen work order: ' . $e->getMessage(),
            ], 500);
        }
    }
}
