<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddFeedbackRequest;
use App\Http\Requests\AssignWorkOrderRequest;
use App\Http\Requests\CancelWorkOrderRequest;
use App\Http\Requests\DuplicateWorkOrderRequest;
use App\Http\Requests\ReopenWorkOrderRequest;
use App\Http\Requests\ScheduleWorkOrderRequest;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Http\Requests\UpdateWorkOrderRequest;
use App\Models\Staff;
use App\Models\WorkOrder;
use App\Models\WorkOrderFile;
use App\QueryFilterTrait;
use App\Services\WorkOrder\WorkOrderAssignmentService;
use App\Services\WorkOrder\WorkOrderFeedbackService;
use App\Services\WorkOrder\WorkOrderFileService;
use App\Services\WorkOrder\WorkOrderService;
use App\Services\WorkOrder\WorkOrderStatusService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    use QueryFilterTrait;

    protected $workOrderService;
    protected $statusService;
    protected $assignmentService;
    protected $fileService;
    protected $feedbackService;

    public function __construct(
        WorkOrderService $workOrderService,
        WorkOrderStatusService $statusService,
        WorkOrderAssignmentService $assignmentService,
        WorkOrderFileService $fileService,
        WorkOrderFeedbackService $feedbackService
    ) {
        $this->workOrderService = $workOrderService;
        $this->statusService = $statusService;
        $this->assignmentService = $assignmentService;
        $this->fileService = $fileService;
        $this->feedbackService = $feedbackService;
    }

    /**
     * List work orders with filters
     */
    public function index(Request $request): JsonResponse
    {
        $page = $request->page ?? 1;
        $perPage = $request->perPage ?? 50;
        $query = WorkOrder::with([
            'customer:id,name,email,phone,whatsapp',
            'address:id,address_line_1,address_line_2,city,state,country,zip_code',
            'brand:id,name',
            'category:id,name',
            'service:id,name',
            'parentService:id,name',
            'product:id,name',
            'status:id,name,color',
            'subStatus:id,name,color',
            'assignedTo:id,first_name,last_name',
            'services',
            'branch:id,name',
            'dealer:id,name',
            'dealerBranch:id,name',
            'city:id,name',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',

        ]);
        // ss
        if ($request->has('status_id') && $request->status_id) {
            $statusIds = is_array($request->status_id)
                ? $request->status_id
                : explode(',', $request->status_id);
            $query->whereIn('status_id', $statusIds);
        }
        if ($request->has('sub_status_id') && $request->sub_status_id) {
            $subStatusIds = is_array($request->sub_status_id)
                ? $request->sub_status_id
                : explode(',', $request->sub_status_id);
            $query->whereIn('sub_status_id', $subStatusIds);
        }
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
                    })
                    ->orWhereHas('address', function ($addressQuery) use ($searchTerm) {
                        $addressQuery->where('address_line_1', 'like', "%{$searchTerm}%")
                            ->orWhere('address_line_2', 'like', "%{$searchTerm}%")
                            ->orWhere('city', 'like', "%{$searchTerm}%")
                            ->orWhere('state', 'like', "%{$searchTerm}%")
                            ->orWhere('country', 'like', "%{$searchTerm}%")
                            ->orWhere('zip_code', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Apply JSON filters from "filters" parameter
        $filtersInput = $request->input('filters');
        
        // Handle simple key-value filters (from dashboard dialogs)
        if (is_array($filtersInput)) {
            // Handle overdue filter
            if (isset($filtersInput['overdue']) && ($filtersInput['overdue'] === '1' || $filtersInput['overdue'] === 1 || $filtersInput['overdue'] === true || $filtersInput['overdue'] === 'true')) {
                $query->whereNotNull('assigned_at')
                    ->where('assigned_at', '<', now()->subDays(2))
                    ->whereNull('completed_at')
                    ->whereNull('cancelled_at');
            }
            
            // Handle created_today filter
            if (isset($filtersInput['created_today']) && ($filtersInput['created_today'] === '1' || $filtersInput['created_today'] === 1 || $filtersInput['created_today'] === true || $filtersInput['created_today'] === 'true')) {
                $query->whereDate('created_at', today());
            }
            
            // Handle closed_today filter
            if (isset($filtersInput['closed_today']) && ($filtersInput['closed_today'] === '1' || $filtersInput['closed_today'] === 1 || $filtersInput['closed_today'] === true || $filtersInput['closed_today'] === 'true')) {
                $query->whereDate('closed_at', today());
            }
            
            // Handle pending filter
            if (isset($filtersInput['pending']) && ($filtersInput['pending'] === '1' || $filtersInput['pending'] === 1 || $filtersInput['pending'] === true || $filtersInput['pending'] === 'true')) {
                $query->whereNull('completed_at')
                    ->whereNull('cancelled_at');
            }
            
            // Handle branch_id filter
            if (isset($filtersInput['branch_id']) && $filtersInput['branch_id']) {
                $query->where('branch_id', $filtersInput['branch_id']);
            }
            
            // Handle city filter (by name)
            if (isset($filtersInput['city']) && $filtersInput['city']) {
                $query->whereHas('city', function($q) use ($filtersInput) {
                    $q->where('name', $filtersInput['city']);
                });
            }
            
            // Handle assigned_to_id / staff_id filter
            if (isset($filtersInput['assigned_to_id']) && $filtersInput['assigned_to_id']) {
                $query->where('assigned_to_id', $filtersInput['assigned_to_id']);
            }
            
            // Handle date_from filter
            if (isset($filtersInput['date_from']) && $filtersInput['date_from']) {
                $query->whereDate('created_at', '>=', $filtersInput['date_from']);
            }
            
            // Handle date_to filter
            if (isset($filtersInput['date_to']) && $filtersInput['date_to']) {
                $query->whereDate('created_at', '<=', $filtersInput['date_to']);
            }
            
            // Handle status_id filter
            if (isset($filtersInput['status_id']) && $filtersInput['status_id']) {
                $statusIds = is_array($filtersInput['status_id'])
                    ? $filtersInput['status_id']
                    : [$filtersInput['status_id']];
                $query->whereIn('status_id', $statusIds);
            }
        }
        
        // Apply the standard JSON filters for array-of-objects format
        $this->applyJsonFilters($query, $request);

        // Apply sorting
        $this->applySorting($query, $request);

        // Fallback to order by status.order, then sub_status.order if no sorting specified
        if (!$request->has('sort')) {
            $query->select('work_orders.*')
                ->leftJoin('work_order_statuses as status_table', 'work_orders.status_id', '=', 'status_table.id')
                ->leftJoin('work_order_statuses as sub_status_table', 'work_orders.sub_status_id', '=', 'sub_status_table.id')
                ->orderBy('status_table.order', 'asc')
                ->orderBy('sub_status_table.order', 'asc')
                ->orderBy('work_orders.created_at', 'desc');
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
    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = $this->workOrderService->createWorkOrder($request->validated(), $user->id);

            return response()->json([
                'status' => "success",
                'message' => 'Work order created successfully',
                'data' => $workOrder->load(['customer', 'address', 'services']),
            ]);
        } catch (Exception $e) {
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
    public function show(string $work_order): JsonResponse
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
            'dealer',
            'dealerBranch',
            'city',
            'files',
        ])->findOrFail($work_order);

        return response()->json([
            'success' => true,
            'data' => $workOrder,
        ]);
    }

    /**
     * Update work order
     */
    public function update(UpdateWorkOrderRequest $request, string $work_order): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($work_order);

        try {
            // Update work order using service (Prevent updates logic moved to service)
            $user = $request->user();
            $this->workOrderService->updateWorkOrder($workOrder, $request->validated(), $user->id);

            return response()->json([
                'status' => "success",
                'message' => 'Work order updated successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Schedule work order appointment
     */
    public function schedule(ScheduleWorkOrderRequest $request, string $work_order): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($work_order);
            $user = $request->user();
            $result = $this->statusService->scheduleWorkOrder($workOrder, $request->validated(), $user->id);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Assign or reassign staff to work order
     */
    public function assign(AssignWorkOrderRequest $request, string $work_order): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($work_order);
            $staff = Staff::findOrFail($request->assigned_to_id);
            if ($staff->status_id !== 1) {
                return response()->json([
                    'status' => "error",
                    'message' => "Staff is not available",
                ], 422);
            }
            $user = $request->user();
            $result = $this->assignmentService->assignStaff(
                $workOrder,
                $request->assigned_to_id,
                $request->notes,
                $user->id
            );

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => "error",
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel work order
     */
    public function cancel(CancelWorkOrderRequest $request, string $work_order): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($work_order);
            $user = $request->user();
            $result = $this->statusService->cancelWorkOrder(
                $workOrder,
                $request->cancellation_reason,
                $user->id
            );

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => "error",
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete work order
     */
    public function destroy(string $work_order): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($work_order);

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
    public function history(string $work_order): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($work_order);

            $histories = $workOrder->histories()
                ->with('user:id,first_name,last_name')
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $histories,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve work order history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get files associated with work order
     */
    public function getFiles(string $work_order): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($work_order);
            $files = $this->fileService->getFiles($workOrder);

            return response()->json([
                'status' => 'success',
                'data' => $files,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get files: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload files to work order
     */
    public function uploadFiles(Request $request, string $work_order): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($work_order);

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:10240', // 10MB limit
            'file_type_id' => 'nullable|exists:service_required_file_types,id',
        ]);

        try {
            $user = $request->user();
            $uploadedFiles = $this->fileService->uploadFiles(
                $workOrder,
                $request->file('files'),
                $request->file_type_id,
                $user->id
            );

            return response()->json([
                'status' => 'success',
                'message' => count($uploadedFiles) . ' files uploaded successfully',
                'data' => $uploadedFiles,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload files: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete work order file
     */
    public function deleteFile(string $work_order, string $fileId): JsonResponse
    {
        try {
            $file = WorkOrderFile::where('work_order_id', $work_order)->findOrFail($fileId);
            $this->fileService->deleteFile($file);

            return response()->json([
                'status' => 'success',
                'message' => 'File deleted successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send reminder notification to assigned staff
     */
    public function sendReminder(Request $request, string $work_order): JsonResponse
    {
        try {
            $data = $request->validate([
                'remark' => 'required|string|max:500',
            ]);

            $user = $request->user();
            $workOrder = WorkOrder::with(['assignedTo'])->findOrFail($work_order);
            $result = $this->workOrderService->sendReminder($workOrder, $data['remark'], $user->id);

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send reminder: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Duplicate work order with selective copying
     */
    public function duplicate(DuplicateWorkOrderRequest $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $originalWorkOrder = WorkOrder::findOrFail($work_order);
            $result = $this->workOrderService->duplicateWorkOrder($originalWorkOrder, $request->validated(), $user->id);

            return response()->json([
                'status' => "success",
                'message' => $result['quantity'] === 1
                    ? "Work order duplicated successfully as #{$result['numbers'][0]}"
                    : "Successfully created {$result['quantity']} work orders",
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => "error",
                'message' => 'Failed to duplicate work order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reopen a work order
     */
    public function reopen(ReopenWorkOrderRequest $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $originalWorkOrder = WorkOrder::findOrFail($work_order);
            $newWorkOrder = $this->workOrderService->reopenWorkOrder($originalWorkOrder, $request->validated(), $user->id);

            return response()->json([
                'status' => 'success',
                'message' => "Work order reopened successfully as #{$newWorkOrder->work_order_number}",
                'data' => $newWorkOrder,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reopen work order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accept work order
     */
    public function acceptWorkOrder(Request $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($work_order);
            $result = $this->statusService->acceptWorkOrder($workOrder, $user->id);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Start service
     */
    public function startService(Request $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($work_order);
            $result = $this->statusService->startService($workOrder, $user->id);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Start work
     */
    public function startWork(Request $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($work_order);
            $result = $this->statusService->startWork($workOrder, $user->id);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Complete service
     */
    public function completeService(Request $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($work_order);
            $result = $this->statusService->completeService($workOrder, $user->id);
            return response()->json($result);
        } catch (\App\Exceptions\MissingFilesException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'missing_files' => $e->getMissingFiles(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mark work order as Part in Demand
     */
    public function markAsPartInDemand(Request $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($work_order);
            $result = $this->statusService->markAsPartInDemand($workOrder, $user->id);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Complete service from Part in Demand status
     */
    public function completeFromPartDemand(Request $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($work_order);
            $result = $this->statusService->completeFromPartDemand($workOrder, $user->id);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get feedback for work order
     */
    public function getFeedback(string $work_order): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($work_order);
            $feedback = $this->feedbackService->getFeedback($workOrder);
            return response()->json([
                'status' => 'success',
                'data' => $feedback,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add feedback for work order
     */
    public function addFeedback(AddFeedbackRequest $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($work_order);
            $feedback = $this->feedbackService->addFeedback($workOrder, $request->validated(), $user->id);
            return response()->json([
                'status' => 'success',
                'message' => 'Feedback added successfully',
                'data' => $feedback,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve work order (after completion)
     */
    public function approveWorkOrder(Request $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = WorkOrder::with('status')->findOrFail($work_order);
            $result = $this->statusService->approveWorkOrder($workOrder, $user->id);
            return response()->json($result);
        } catch (\App\Exceptions\MissingFilesException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'missing_files' => $e->getMissingFiles(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject completion and send back to rework
     */
    public function rejectCompletion(Request $request, string $work_order): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $user = $request->user();
            $workOrder = WorkOrder::with('status')->findOrFail($work_order);
            $result = $this->statusService->rejectCompletion($workOrder, $request->reason, $user->id);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Close work order (from Feedback Pending status)
     */
    public function closeWorkOrder(Request $request, string $work_order): JsonResponse
    {
        try {
            $user = $request->user();
            $workOrder = WorkOrder::with(['status', 'subStatus'])->findOrFail($work_order);
            $result = $this->statusService->closeWorkOrder($workOrder, $user->id);
            return response()->json($result);
        } catch (\App\Exceptions\MissingFilesException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'missing_files' => $e->getMissingFiles(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Sync quick charges for work order
     */
    public function syncCharges(Request $request, string $work_order): JsonResponse
    {
        $request->validate([
            'charges' => 'nullable|array',
            'charges.items' => 'nullable|array',
            'total_amount' => 'nullable|numeric',
        ]);

        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($work_order);
            $workOrder = $this->workOrderService->syncCharges($workOrder, $request->all(), $user->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Charges synced successfully',
                'data' => $workOrder,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to sync charges: ' . $e->getMessage(),
            ], 500);
        }
    }
}
