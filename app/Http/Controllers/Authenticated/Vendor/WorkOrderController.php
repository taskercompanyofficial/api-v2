<?php

namespace App\Http\Controllers\Authenticated\Vendor;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderHistory;
use App\Models\WorkOrderFile;
use App\Models\FileType;
use App\Models\ServiceConcern;
use App\Models\ServiceSubConcern;
use App\Models\WarrantyType;
use App\Services\WorkOrder\WorkOrderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class WorkOrderController extends Controller
{
    protected $notificationService;
    protected $statusService;

    public function __construct(
        WorkOrderNotificationService $notificationService,
        \App\Services\WorkOrder\WorkOrderStatusService $statusService
    ) {
        $this->notificationService = $notificationService;
        $this->statusService = $statusService;
    }

    /**
     * Resolve the vendor ID from the authenticated user.
     * Works for both Vendor and VendorStaff users.
     */
    protected function resolveVendorId(Request $request): int
    {
        $user = $request->user();
        if ($user instanceof \App\Models\VendorStaff) {
            return $user->vendor_id;
        }
        return $user->id;
    }

    /**
     * Check if the authenticated user is a VendorStaff
     */
    protected function isStaffUser(Request $request): bool
    {
        return $request->user() instanceof \App\Models\VendorStaff;
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
            $vendorId = $this->resolveVendorId($request);

            $baseQuery = WorkOrder::where('assigned_vendor_id', $vendorId);

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
     * Get work orders assigned to authenticated vendor
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $vendor = $request->user();

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
            ])->where('assigned_vendor_id', $this->resolveVendorId($request));

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

            if ($this->isStaffUser($request)) {
                $workOrders->each(function (\App\Models\WorkOrder $order) {
                    $order->makeHidden([
                        'total_amount',
                        'discount',
                        'final_amount',
                        'payment_status',
                        'charges',
                        'attachments'
                    ]);
                });
            }

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
            $vendor = $request->user();

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
                'assignedVendor',
                'files' => function ($query) use ($request) {
                    $user = $request->user();
                    // Resolve the ID used for filtering uploaded files. 
                    // If it's a vendor, we use their ID. If it's staff, we use their ID? 
                    // Actually, files are often filtered by vendor_id or uploaded_by_id. 
                    // Assuming uploaded_by_id is what's used here.
                    $query->where('uploaded_by_id', $user->id);
                },
                'files.fileType',
                'serviceConcern',
                'serviceSubConcern',
            ])->where('assigned_vendor_id', $this->resolveVendorId($request))
                ->findOrFail($id);

            $data = $workOrder->fresh([
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
                'assignedVendor',
                'files' => function ($query) use ($request) {
                    $query->where('uploaded_by_id', $request->user()->id);
                },
                'files.fileType',
                'serviceConcern',
                'serviceSubConcern',
                'warrantyType',
                'vendorStaff',
            ]);

            if ($this->isStaffUser($request)) {
                $data->makeHidden([
                    'total_amount',
                    'discount',
                    'final_amount',
                    'payment_status',
                    'charges',
                    'attachments'
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $data,
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
            $vendor = $request->user();

            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)
                ->findOrFail($id);

            if ($workOrder->accepted_at) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Work order already accepted',
                ], 400);
            }

            // Find "Technician Accepted" sub-status (Vendors use the same status flow for now)
            $dispatchedStatus = \App\Models\WorkOrderStatus::where('slug', 'dispatched')->first();
            $technicianAcceptedStatus = \App\Models\WorkOrderStatus::where('slug', 'technician-accepted')
                ->where('parent_id', $dispatchedStatus?->id)
                ->first();

            $workOrder->accepted_at = now();
            if ($technicianAcceptedStatus) {
                $workOrder->sub_status_id = $technicianAcceptedStatus->id;
            }
            // Use something to track who updated it.
            // Vendors don't have updated_by as foreign key to vendors yet in the migration I saw (it was staff)
            // But we can store it if needed. Check migration again.
            // $workOrder->updated_by = $vendor->id; 
            $workOrder->save();

            // Send notification
            $this->notificationService->workOrderAccepted($workOrder, $vendor->id, true); // Added true for vendor

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
            $vendor = $request->user();

            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)
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
            $workOrder->rejected_by = "Vendor: " . $vendor->name;
            $workOrder->save();

            // Send notification
            $this->notificationService->workOrderRejected($workOrder, $vendor->id, $request->reason, true);

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
            'service_concern_id' => 'nullable|exists:service_concerns,id',
            'service_sub_concern_id' => 'nullable|exists:service_sub_concerns,id',
            'warranty_type_id' => 'nullable|exists:warranty_types,id',
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
            $vendor = $request->user();

            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)
                ->findOrFail($id);

            $workOrder->fill($request->only([
                'service_concern_id',
                'service_sub_concern_id',
                'warranty_type_id',
                'indoor_serial_number',
                'outdoor_serial_number',
                'product_indoor_model',
                'product_outdoor_model',
                'purchase_date',
                'warranty_serial_number',
                'customer_description',
                'technician_remarks',
            ]));

            $workOrder->save();

            // Send notification
            $this->notificationService->workOrderUpdated($workOrder, $vendor->id, true);

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
            $vendor = $request->user();

            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)
                ->findOrFail($id);

            $workOrder->status_id = $request->status_id;
            if ($request->has('sub_status_id')) {
                $workOrder->sub_status_id = $request->sub_status_id;
            }
            $workOrder->save();

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
            $vendor = $request->user();

            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)
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

            $workOrder->status_id = $status->id;
            $workOrder->sub_status_id = $subStatus->id;

            // Update service start/end times based on status
            if ($request->status_slug === 'in-progress' && $request->sub_status_slug === 'going-to-work') {
                $workOrder->service_start_date = now()->toDateString();
                $workOrder->service_start_time = now()->toTimeString();
            }

            if ($request->status_slug === 'completed') {
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
                $workOrder->completed_by = "Vendor: " . $vendor->name;
            }


            // Append remarks to technician_remarks if provided
            if ($request->remarks) {
                $currentRemarks = $workOrder->technician_remarks ?? '';

                // Build the remark entry with timestamp and status info
                $timestamp = now()->format('Y-m-d H:i');
                $statusInfo = "{$subStatus->name}";

                // Add location if provided
                $locationInfo = '';
                if ($request->address) {
                    $locationInfo = " (Location: {$request->address})";
                } elseif ($request->latitude && $request->longitude) {
                    $locationInfo = " (Coordinates: {$request->latitude}, {$request->longitude})";
                }

                $remarkEntry = "[{$timestamp}] Vendor updated status to '{$statusInfo}'{$locationInfo}: {$request->remarks}";

                $newRemarks = $currentRemarks
                    ? $currentRemarks . "\n\n" . $remarkEntry
                    : $remarkEntry;

                $workOrder->technician_remarks = $newRemarks;
            }

            $workOrder->save();

            // Send notification based on status
            if ($request->status_slug === 'completed') {
                $this->notificationService->workOrderCompleted($workOrder, $vendor->id, true);
            } elseif ($request->status_slug === 'in-progress' && $request->sub_status_slug === 'going-to-work') {
                $this->notificationService->serviceStarted($workOrder, $vendor->id, true);
            } elseif ($request->status_slug === 'in-progress' && $request->sub_status_slug === 'work-started') {
                $this->notificationService->workStarted($workOrder, $vendor->id, true);
            } elseif ($request->status_slug === 'in-progress' && $request->sub_status_slug === 'part-in-demand') {
                $this->notificationService->partInDemand($workOrder, $vendor->id, true);
            } else {
                $this->notificationService->workOrderUpdated($workOrder, $vendor->id, true);
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
                    'assignedVendor',
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

    /**
     * Schedule work order (vendor app)
     */
    public function schedule(string $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'required|string',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $vendor = $request->user();
            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)->findOrFail($id);

            $result = $this->statusService->scheduleWorkOrder($workOrder, $request->all(), $vendor->id);

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel work order (vendor app)
     */
    public function cancel(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $vendor = $request->user();
            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)->findOrFail($id);

            $result = $this->statusService->cancelWorkOrder($workOrder, $request->reason, $vendor->id);

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign staff to work order
     */
    public function assignStaff(string $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vendor_staff_id' => 'required|exists:vendor_staff,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $vendor = $request->user();
            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)
                ->findOrFail($id);

            // Verify staff belongs to this vendor and is approved
            $staff = \App\Models\VendorStaff::where('vendor_id', $vendor->id)
                ->where('id', $request->vendor_staff_id)
                ->where('status', 'approved')
                ->first();

            if (!$staff) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or unapproved staff member selected.',
                ], 422);
            }

            $workOrder->vendor_staff_id = $staff->id;
            $workOrder->save();

            return response()->json([
                'status' => 'success',
                'message' => "Work order assigned to {$staff->name} successfully.",
                'data' => $workOrder->fresh(['vendorStaff']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload file for work order
     */
    public function uploadFile(Request $request, $id)
    {
        $request->validate([
            'file_type_id' => 'required|exists:file_types,id',
            'file' => 'required|file|max:20480', // 20MB
        ]);

        try {
            $vendor = $request->user();
            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)->findOrFail($id);
            $fileType = FileType::findOrFail($request->file_type_id);

            // Get uploaded file
            $uploadedFile = $request->file('file');

            // Generate unique filename
            $extension = $uploadedFile->getClientOriginalExtension();
            $randomString = Str::random(8);
            $fileName = $fileType->slug . '_' . $randomString . '.' . $extension;

            // Store file
            $filePath = $uploadedFile->storeAs(
                'work-orders/' . $workOrder->work_order_number,
                $fileName,
                'public'
            );

            // Create file record
            $file = WorkOrderFile::create([
                'work_order_id' => $workOrder->id,
                'file_type_id' => $request->file_type_id,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $fileType->slug,
                'file_size_kb' => round($uploadedFile->getSize() / 1024, 2),
                'mime_type' => $uploadedFile->getMimeType(),
                'uploaded_by_id' => $vendor->id,
                'uploaded_at' => now(),
                'approval_status' => 'pending',
                'is_vendor' => true,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'File uploaded successfully',
                'data' => $file->load('fileType'),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete file for work order
     */
    public function deleteFile(Request $request, $id, $fileId)
    {
        try {
            $vendor = $request->user();
            $workOrder = WorkOrder::where('assigned_vendor_id', $vendor->id)->findOrFail($id);

            $file = WorkOrderFile::where('work_order_id', $workOrder->id)
                ->where('id', $fileId)
                ->where('uploaded_by_id', $vendor->id)
                ->firstOrFail();

            // Delete from storage
            if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }

            $file->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'File deleted successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all service concerns
     */
    public function getServiceConcerns()
    {
        try {
            $concerns = ServiceConcern::where('status', 'active')->get();
            return response()->json(['data' => $concerns]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to fetch concerns', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get sub-concerns for a given concern
     */
    public function getServiceSubConcerns($id)
    {
        try {
            $subConcerns = ServiceSubConcern::where('service_concern_id', $id)
                ->where('status', 'active')
                ->get();
            return response()->json(['data' => $subConcerns]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to fetch sub-concerns', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all warranty types
     */
    public function getWarrantyTypes()
    {
        try {
            $types = WarrantyType::where('status', 'active')->get();
            return response()->json(['data' => $types]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to fetch warranty types', 'error' => $e->getMessage()], 500);
        }
    }
}
