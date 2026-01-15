<?php

namespace App\Http\Controllers\Authenticated\StaffApp;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderHistory;
use App\Models\WorkOrderFile;
use App\Services\WorkOrder\WorkOrderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class WorkOrderController extends Controller
{
    protected WorkOrderNotificationService $notificationService;

    public function __construct(WorkOrderNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all file types
     */
    public function getFileTypes(): JsonResponse
    {
        try {
            $fileTypes = \App\Models\FileType::active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'mime_types']);

            return response()->json([
                'status' => 'success',
                'data' => $fileTypes,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get work order summary/statistics for dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $staff = $request->user();

            $baseQuery = WorkOrder::where('assigned_to_id', $staff->id);

            // Total Work Orders
            $total = (clone $baseQuery)->count();

            // Pending Acceptance
            $pendingAcceptance = (clone $baseQuery)
                ->whereNull('accepted_at')
                ->whereNull('rejected_at')
                ->whereHas('status', function ($q) {
                    $q->where('slug', 'dispatched');
                })->count();

            // In Progress/Active
            $active = (clone $baseQuery)
                ->whereNotNull('accepted_at')
                ->whereNull('completed_at')
                ->whereNull('cancelled_at')
                ->whereHas('status', function ($q) {
                    $q->whereIn('slug', ['dispatched', 'in-progress']);
                })->count();

            // Completed (Total)
            $completed = (clone $baseQuery)
                ->whereNotNull('completed_at')
                ->count();

            // Completed this month
            $completedThisMonth = (clone $baseQuery)
                ->whereNotNull('completed_at')
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total' => $total,
                    'pending_acceptance' => $pendingAcceptance,
                    'active' => $active,
                    'completed' => $completed,
                    'completed_this_month' => $completedThisMonth,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get work orders assigned to authenticated staff
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $staff = $request->user();

            $query = WorkOrder::with([
                'customer:id,name,phone,email,whatsapp',
                'address:id,address_line_1,address_line_2,city,state,zip_code',
                'status:id,name,slug',
                'subStatus:id,name,slug',
                'category:id,name',
                'service:id,name',
                'brand:id,name',
                'serviceConcern:id,name',
                'serviceSubConcern:id,name',
            ])->where('assigned_to_id', $staff->id);

            // Search by work order number or customer phone
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('work_order_number', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('phone', 'LIKE', "%{$search}%");
                        });
                });
            }

            // Filter by status
            if ($request->has('status')) {
                $statusFilter = $request->status;

                if ($statusFilter === 'pending') {
                    // Accept Pending - Dispatched but not yet accepted
                    $query->whereNull('accepted_at')
                        ->whereNull('rejected_at')
                        ->whereHas('status', function ($q) {
                            $q->where('slug', 'dispatched');
                        });
                } elseif ($statusFilter === 'active') {
                    // In Progress - Accepted work orders that are not completed
                    $query->whereNotNull('accepted_at')
                        ->whereNull('completed_at')
                        ->whereNull('cancelled_at')
                        ->whereHas('status', function ($q) {
                            $q->whereIn('slug', ['dispatched', 'in-progress']);
                        });
                } elseif ($statusFilter === 'completed') {
                    // Completed work orders
                    $query->whereHas('status', function ($q) {
                        $q->whereIn('slug', ['completed']);
                    });
                }
            }

            $workOrders = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $workOrders,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get work order details
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $staff = $request->user();

            $workOrder = WorkOrder::with([
                'customer',
                'address',
                'status',
                'subStatus',
                'category',
                'service',
                'parentService.requiredFileTypes.fileType',
                'product',
                'brand',
                'branch',
                'assignedTo',
                'files.fileType',
                'serviceConcern',
                'serviceSubConcern',
            ])->where('assigned_to_id', $staff->id)
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $workOrder->fresh([
                    'customer',
                    'address',
                    'status',
                    'subStatus',
                    'category',
                    'service',
                    'parentService.requiredFileTypes.fileType',
                    'product',
                    'brand',
                    'branch',
                    'assignedTo',
                    'files.fileType',
                    'serviceConcern',
                    'serviceSubConcern',
                    'warrantyType',
                ]),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Accept work order
     */
    public function accept(string $id, Request $request): JsonResponse
    {
        try {
            $staff = $request->user();

            $workOrder = WorkOrder::where('assigned_to_id', $staff->id)
                ->findOrFail($id);

            if ($workOrder->accepted_at) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Work order already accepted',
                ], 400);
            }

            // Find "Technician Accepted" sub-status
            $dispatchedStatus = \App\Models\WorkOrderStatus::where('slug', 'dispatched')->first();
            $technicianAcceptedStatus = \App\Models\WorkOrderStatus::where('slug', 'technician-accepted')
                ->where('parent_id', $dispatchedStatus?->id)
                ->first();

            $workOrder->accepted_at = now();
            if ($technicianAcceptedStatus) {
                $workOrder->sub_status_id = $technicianAcceptedStatus->id;
            }
            $workOrder->updated_by = $staff->id;
            $workOrder->save();

            // Send notification
            $this->notificationService->workOrderAccepted($workOrder, $staff->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Work order accepted successfully',
                'data' => $workOrder->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject work order
     */
    public function reject(string $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $staff = $request->user();

            $workOrder = WorkOrder::where('assigned_to_id', $staff->id)
                ->findOrFail($id);

            // Find Cancelled status with "Technician Rejected" sub-status
            $cancelledStatus = \App\Models\WorkOrderStatus::where('slug', 'cancelled')->first();
            $technicianRejectedSubStatus = \App\Models\WorkOrderStatus::where('slug', 'technician-rejected')
                ->where('parent_id', $cancelledStatus?->id)
                ->first();

            $workOrder->status_id = $cancelledStatus?->id;
            $workOrder->sub_status_id = $technicianRejectedSubStatus?->id;
            $workOrder->reject_reason = $request->reason;
            $workOrder->rejected_at = now();
            $workOrder->rejected_by = $staff->id;
            $workOrder->updated_by = $staff->id;
            $workOrder->save();

            // Send notification
            $this->notificationService->workOrderRejected($workOrder, $staff->id, $request->reason);

            return response()->json([
                'status' => 'success',
                'message' => 'Work order rejected',
                'data' => $workOrder->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update work order details (product info, serials, etc.)
     */
    public function updateDetails(string $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'indoor_serial_number' => 'nullable|string|max:255',
            'outdoor_serial_number' => 'nullable|string|max:255',
            'product_indoor_model' => 'nullable|string|max:255',
            'product_outdoor_model' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'warranty_serial_number' => 'nullable|string|max:255',
            'customer_description' => 'nullable|string',
            'technician_remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $staff = $request->user();

            $workOrder = WorkOrder::where('assigned_to_id', $staff->id)
                ->findOrFail($id);

            $workOrder->fill($request->only([
                'indoor_serial_number',
                'outdoor_serial_number',
                'product_indoor_model',
                'product_outdoor_model',
                'purchase_date',
                'warranty_serial_number',
                'customer_description',
                'technician_remarks',
            ]));

            $workOrder->updated_by = $staff->id;
            $workOrder->save();

            // Send notification
            $this->notificationService->workOrderUpdated($workOrder, $staff->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Work order details updated successfully',
                'data' => $workOrder->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Update work order status
     */
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status_id' => 'required|exists:work_order_statuses,id',
            'sub_status_id' => 'nullable|exists:work_order_statuses,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $staff = $request->user();

            $workOrder = WorkOrder::where('assigned_to_id', $staff->id)
                ->findOrFail($id);

            $oldStatus = $workOrder->status;
            $newStatus = \App\Models\WorkOrderStatus::findOrFail($request->status_id);

            $workOrder->status_id = $request->status_id;
            if ($request->has('sub_status_id')) {
                $workOrder->sub_status_id = $request->sub_status_id;
            }
            $workOrder->updated_by = $staff->id;
            $workOrder->save();

            // // Log status change
            // WorkOrderHistory::log(
            //     workOrderId: $workOrder->id,
            //     actionType: 'status_updated',
            //     description: "Status changed from {$oldStatus->name} to {$newStatus->name} by {$staff->first_name} {$staff->last_name}",
            //     fieldName: 'status',
            //     oldValue: $oldStatus->name,
            //     newValue: $newStatus->name,
            //     metadata: [
            //         'notes' => $request->notes,
            //     ]
            // );

            return response()->json([
                'status' => 'success',
                'message' => 'Work order status updated successfully',
                'data' => $workOrder->fresh(['status', 'subStatus']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update work order status by slug (easier for frontend)
     */
    public function updateStatusBySlug(string $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status_slug' => 'required|string',
            'sub_status_slug' => 'required|string',
            'remarks' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $staff = $request->user();

            $workOrder = WorkOrder::where('assigned_to_id', $staff->id)
                ->findOrFail($id);

            // Find status by slug
            $status = \App\Models\WorkOrderStatus::where('slug', $request->status_slug)
                ->whereNull('parent_id')
                ->first();

            if (!$status) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Status not found',
                ], 404);
            }

            // Find sub-status by slug
            $subStatus = \App\Models\WorkOrderStatus::where('slug', $request->sub_status_slug)
                ->where('parent_id', $status->id)
                ->first();

            if (!$subStatus) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub-status not found',
                ], 404);
            }

            $oldStatus = $workOrder->status;
            $oldSubStatus = $workOrder->subStatus;

            $workOrder->status_id = $status->id;
            $workOrder->sub_status_id = $subStatus->id;

            // Update service start/end times based on status
            if ($request->status_slug === 'in-progress' && $request->sub_status_slug === 'going-to-work') {
                $workOrder->service_start_date = now()->toDateString();
                $workOrder->service_start_time = now()->toTimeString();
            }

            if ($request->status_slug === 'completed') {
                // Check for required files
                $requiredFiles = \App\Models\ServiceRequiredFile::where('parent_service_id', $workOrder->parent_service_id)
                    ->where('is_required', true)
                    ->with('fileType')
                    ->get();

                if ($requiredFiles->isNotEmpty()) {
                    $uploadedFileTypeIds = $workOrder->files()->pluck('file_type_id')->unique()->toArray();
                    $missingFiles = $requiredFiles->filter(function ($req) use ($uploadedFileTypeIds) {
                        return !in_array($req->file_type_id, $uploadedFileTypeIds);
                    });

                    if ($missingFiles->isNotEmpty()) {
                        $missingNames = $missingFiles->map(function ($req) {
                            return $req->fileType->name ?? 'Unknown Type';
                        })->implode(', ');

                        return response()->json([
                            'status' => 'error',
                            'message' => "Required files missing: {$missingNames}. Please upload them before completing.",
                        ], 422);
                    }
                }

                $workOrder->service_end_date = now()->toDateString();
                $workOrder->service_end_time = now()->toTimeString();
                $workOrder->completed_at = now();
                $workOrder->completed_by = $staff->id;
            }
            if ($workOrder->warranty_type_id == 1) {
                $missingFields = [];
                if (!$workOrder->indoor_serial_number) {
                    $missingFields[] = 'Indoor Serial Number';
                }
                if (!$workOrder->outdoor_serial_number) {
                    $missingFields[] = 'Outdoor Serial Number';
                }
                if (!$workOrder->product_indoor_model) {
                    $missingFields[] = 'Product Indoor Model';
                }
                if (!$workOrder->product_outdoor_model) {
                    $missingFields[] = 'Product Outdoor Model';
                }
                if (!$workOrder->purchase_date) {
                    $missingFields[] = 'Purchase Date';
                }

                if (!empty($missingFields)) {
                    $fieldsList = implode(', ', $missingFields);
                    return response()->json([
                        'status' => 'error',
                        'message' => "Warranty information is incomplete. Missing: {$fieldsList}. Please update the work order before completing.",
                    ], 422);
                }
            }

            // Append remarks to technician_remarks if provided
            if ($request->remarks) {
                $currentRemarks = $workOrder->technician_remarks ?? '';

                // Build the remark entry with timestamp and status info
                $timestamp = now()->format('Y-m-d H:i');
                $statusInfo = "{$subStatus->name}";

                // Add location if provided (prefer address over coordinates)
                $locationInfo = '';
                if ($request->address) {
                    $locationInfo = " (Location: {$request->address})";
                } elseif ($request->latitude && $request->longitude) {
                    $locationInfo = " (Coordinates: {$request->latitude}, {$request->longitude})";
                }

                $remarkEntry = "[{$timestamp}] Technician updated status to '{$statusInfo}'{$locationInfo}: {$request->remarks}";

                $newRemarks = $currentRemarks
                    ? $currentRemarks . "\n\n" . $remarkEntry
                    : $remarkEntry;

                $workOrder->technician_remarks = $newRemarks;
            }

            $workOrder->updated_by = $staff->id;
            $workOrder->save();

            // Send notification based on status
            if ($request->status_slug === 'completed') {
                $this->notificationService->workOrderCompleted($workOrder, $staff->id);
            } elseif ($request->status_slug === 'in-progress' && $request->sub_status_slug === 'going-to-work') {
                $this->notificationService->serviceStarted($workOrder, $staff->id);
            } elseif ($request->status_slug === 'in-progress' && $request->sub_status_slug === 'work-started') {
                $this->notificationService->workStarted($workOrder, $staff->id);
            } elseif ($request->status_slug === 'in-progress' && $request->sub_status_slug === 'part-in-demand') {
                $this->notificationService->partInDemand($workOrder, $staff->id);
            } else {
                // Generic status update notification
                $this->notificationService->workOrderUpdated($workOrder, $staff->id);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Work order status updated successfully',
                'data' => $workOrder->fresh([
                    'customer',
                    'address',
                    'status',
                    'subStatus',
                    'category',
                    'service',
                    'parentService',
                    'product',
                    'brand',
                    'branch',
                    'assignedTo',
                    'files.fileType',
                ]),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
