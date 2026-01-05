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
    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        try {
            $workOrder = $this->workOrderService->createWorkOrder($request->validated(), auth()->id());

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
            'dealer',
            'dealerBranch',
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
    public function update(UpdateWorkOrderRequest $request, string $id): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($id);

        try {
            // Update work order using service (Prevent updates logic moved to service)
            $this->workOrderService->updateWorkOrder($workOrder, $request->validated(), auth()->id());

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
    public function schedule(ScheduleWorkOrderRequest $request, string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $result = $this->statusService->scheduleWorkOrder($workOrder, $request->validated(), auth()->id());
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
    public function assign(AssignWorkOrderRequest $request, string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $staff = Staff::findOrFail($request->assigned_to_id);
            if($staff->status_id !== 1){
                return response()->json([
                    'status' => "error",
                    'message' => "Staff is not available",
                ], 422);
            }
            $result = $this->assignmentService->assignStaff(
                $workOrder,
                $request->assigned_to_id,
                $request->notes,
                auth()->id()
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
    public function cancel(CancelWorkOrderRequest $request, string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $result = $this->statusService->cancelWorkOrder(
                $workOrder,
                $request->cancellation_reason,
                auth()->id()
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
                'message' => 'Failed to retrieve work order history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get files associated with work order
     */
    public function getFiles(string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
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
    public function uploadFiles(Request $request, string $id): JsonResponse
    {
        $workOrder = WorkOrder::findOrFail($id);

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:10240', // 10MB limit
            'file_type_id' => 'nullable|exists:service_required_file_types,id',
        ]);

        try {
            $uploadedFiles = $this->fileService->uploadFiles(
                $workOrder,
                $request->file('files'),
                $request->file_type_id,
                auth()->id()
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
    public function deleteFile(string $id, string $fileId): JsonResponse
    {
        try {
            $file = WorkOrderFile::where('work_order_id', $id)->findOrFail($fileId);
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
    public function sendReminder(Request $request, string $id): JsonResponse
    {
        try {
            $data = $request->validate([
                'remark' => 'required|string|max:500',
            ]);

            $workOrder = WorkOrder::with(['assignedTo'])->findOrFail($id);
            $result = $this->workOrderService->sendReminder($workOrder, $data['remark'], auth()->id());

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
    public function duplicate(DuplicateWorkOrderRequest $request, string $id): JsonResponse
    {
        try {
            $originalWorkOrder = WorkOrder::findOrFail($id);
            $result = $this->workOrderService->duplicateWorkOrder($originalWorkOrder, $request->validated(), auth()->id());

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
    public function reopen(ReopenWorkOrderRequest $request, string $id): JsonResponse
    {
        try {
            $originalWorkOrder = WorkOrder::findOrFail($id);
            $newWorkOrder = $this->workOrderService->reopenWorkOrder($originalWorkOrder, $request->validated(), auth()->id());

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
    public function acceptWorkOrder(Request $request, string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $result = $this->statusService->acceptWorkOrder($workOrder, auth()->id());
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
    public function startService(Request $request, string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $result = $this->statusService->startService($workOrder, auth()->id());
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
    public function startWork(Request $request, string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $result = $this->statusService->startWork($workOrder, auth()->id());
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
    public function completeService(Request $request, string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $result = $this->statusService->completeService($workOrder, auth()->id());
            return response()->json($result);
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
    public function markAsPartInDemand(string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $result = $this->statusService->markAsPartInDemand($workOrder, auth()->id());
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
    public function completeFromPartDemand(Request $request, string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $result = $this->statusService->completeFromPartDemand($workOrder, auth()->id());
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
    public function getFeedback(string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
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
    public function addFeedback(AddFeedbackRequest $request, string $id): JsonResponse
    {
        try {
            $workOrder = WorkOrder::findOrFail($id);
            $feedback = $this->feedbackService->addFeedback($workOrder, $request->validated(), auth()->id());
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

}
