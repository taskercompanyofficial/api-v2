<?php

namespace App\Http\Controllers\TaskerApp;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ParentServices;
use App\Models\WorkOrder;
use App\Models\WorkOrderService as WOService;
use App\Models\WorkOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    /**
     * Get trending parent services based on work order services.
     * Supports optional date range and limit.
     * Query params:
     * - start_date (Y-m-d)
     * - end_date (Y-m-d)
     * - limit (int, default 10)
     */
    public function trendingParentServices(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = $validated['limit'] ?? 10;

        $query = WorkOrder::query()
            ->select([
                'parent_service_id',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('SUM(COALESCE(final_amount, total_amount, 0)) as revenue'),
                DB::raw('MAX(created_at) as last_used_at'),
            ])
            ->whereNotNull('parent_service_id');

        if (!empty($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }
        if (!empty($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        $stats = $query
            ->groupBy('parent_service_id')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get();

        if ($stats->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        $ids = $stats->pluck('parent_service_id')->filter()->unique()->values();
        $services = ParentServices::whereIn('id', $ids)
            ->select(
                'id',
                'name',
                'slug',
                'image',
                'price',
                'tags',
                'description',
                'discount',
                'discount_type',
                'discount_start_date',
                'discount_end_date',
                'includes',
                'excludes',
                'notes'
            )
            ->get()
            ->keyBy('id');

        $data = [];
        foreach ($stats as $row) {
            $svc = $services->get($row->parent_service_id);
            if (!$svc) {
                // Skip missing services to avoid "Parent Service not found" errors
                continue;
            }
            $data[] = [
                'id' => $svc->id,
                'name' => $svc->name,
                'description' => $svc->description,
                'slug' => $svc->slug,
                'image' => $svc->image,
                'price' => $svc->price,
                'tags' => $svc->tags ?? [],
                'discount' => $svc->discount,
                'discount_type' => $svc->discount_type,
                'discount_start_date' => $svc->discount_start_date,
                'discount_end_date' => $svc->discount_end_date,
                'includes' => $svc->includes ?? [],
                'excludes' => $svc->excludes ?? [],
                'notes' => $svc->notes,
                'usage_count' => (int) $row->usage_count,
                'revenue' => (float) $row->revenue,
                'last_used_at' => $row->last_used_at,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'items' => 'required|array|min:1',
            'items.*.parent_service_id' => 'required|exists:parent_services,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|string|in:percentage,fixed',
            'items.*.scheduled_date' => 'nullable|date_format:Y-m-d',
            'items.*.scheduled_time' => 'nullable|date_format:H:i:s',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|string|max:50',
        ]);

        $customer = $request->user();

        try {
            DB::beginTransaction();

            $allocatedStatus = WorkOrderStatus::where('slug', 'allocated')->first();
            $justLaunched = WorkOrderStatus::where('slug', 'just-launched')
                ->where('parent_id', $allocatedStatus?->id)
                ->first();

            $firstItem = $validated['items'][0];
            $appointmentDate = $firstItem['scheduled_date'] ?? null;
            $appointmentTime = $firstItem['scheduled_time'] ?? null;
            $firstParentService = ParentServices::with('service')->find($firstItem['parent_service_id']);
            $firstServiceId = $firstParentService?->service_id;
            $firstCategoryId = $firstParentService?->service?->category_id;

            $workOrder = WorkOrder::create([
                'work_order_number' => WorkOrder::generateNumber(),
                'customer_id' => $customer->id,
                'customer_address_id' => $validated['customer_address_id'],
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'customer_description' => $validated['notes'] ?? null,
                'status_id' => $allocatedStatus?->id,
                'sub_status_id' => $justLaunched?->id,
                'parent_service_id' => $firstItem['parent_service_id'],
                'service_id' => $firstServiceId,
                'category_id' => $firstCategoryId,
            ]);

            foreach ($validated['items'] as $item) {
                $discount = (float)($item['discount'] ?? 0);
                $unitPrice = (float)$item['unit_price'];
                $quantity = (int)$item['quantity'];
                $discountType = $item['discount_type'] ?? 'fixed';

                $effectiveUnit = $discountType === 'percentage'
                    ? max(0, $unitPrice * (1 - ($discount / 100)))
                    : max(0, $unitPrice - $discount);
                $finalPrice = round($effectiveUnit * $quantity, 2);

                $ps = ParentServices::with('service')->find($item['parent_service_id']);
                $serviceId = $ps?->service_id;
                $categoryId = $ps?->service?->category_id;

                WOService::create([
                    'work_order_id' => $workOrder->id,
                    'category_id' => $categoryId,
                    'service_id' => $serviceId,
                    'parent_service_id' => $item['parent_service_id'],
                    'quantity' => $quantity,
                    'service_name' => $ps?->name ?? 'Service',
                    'service_type' => 'paid',
                    'base_price' => $unitPrice,
                    'brand_tariff_price' => 0,
                    'final_price' => $finalPrice,
                    'is_warranty_covered' => false,
                    'status' => 'pending',
                ]);
            }

            $workOrder->calculateTotal();

            Notification::createNotification(
                $customer->id,
                'App\Models\Customer',
                'Work Order Created',
                "Your work order #{$workOrder->work_order_number} has been created.",
                'work-order',
                [
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                ]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Work order created successfully',
                'work_order_number' => $workOrder->work_order_number,
                'data' => $workOrder->fresh(['services']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create work order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function customerWorkOrders(Request $request)
    {
        $customer = $request->user();
        $perPage = (int) ($request->get('perPage', 10));
        $orders = WorkOrder::with(['services.parentService', 'status', 'subStatus'])
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Transform to mobile app "Order" shape
        $collection = $orders->getCollection()->map(function (WorkOrder $wo) {
            $statusSlug = $wo->status?->slug ?? null;
            $subStatusSlug = $wo->subStatus?->slug ?? null;
            // Map CRM status/sub-status to app order status
            $mappedStatus = 'in_progress';
            if ($statusSlug === 'completed') {
                $mappedStatus = 'completed';
            } elseif ($statusSlug === 'cancelled') {
                $mappedStatus = 'cancelled';
            } elseif ($statusSlug === 'allocated' || $statusSlug === 'scheduled' || $subStatusSlug === 'just-launched') {
                $mappedStatus = 'pending';
            }

            return [
                'id' => $wo->id,
                'customer_id' => $wo->customer_id,
                'customer_address_id' => $wo->customer_address_id,
                'order_number' => $wo->work_order_number,
                'order_date' => optional($wo->created_at)->toDateTimeString(),
                'notes' => $wo->customer_description,
                'payment_method' => 'cash',
                'payment_status' => 'pending',
                'total_amount' => (float) ($wo->total_amount ?? 0),
                'paid_amount' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'status' => $mappedStatus,
                'confirmed_at' => null,
                'completed_at' => optional($wo->completed_at)->toDateTimeString(),
                'cancelled_at' => optional($wo->cancelled_at)->toDateTimeString(),
                'created_at' => optional($wo->created_at)->toDateTimeString(),
                'updated_at' => optional($wo->updated_at)->toDateTimeString(),
                // Minimal items mapping (optional for list view)
                'items' => $wo->services->map(function ($svc) {
                    return [
                        'id' => $svc->id,
                        'order_id' => $svc->work_order_id,
                        'parent_service_id' => $svc->parent_service_id,
                        'quantity' => 1,
                        'unit_price' => (float) ($svc->base_price ?? 0),
                        'discount' => 0,
                        'discount_type' => 'fixed',
                        'subtotal' => (float) ($svc->final_price ?? $svc->base_price ?? 0),
                        'scheduled_date' => null,
                        'scheduled_time' => null,
                        'status' => 'pending',
                        'assigned_technician_id' => null,
                        'assigned_at' => null,
                        'started_at' => null,
                        'completed_at' => null,
                        'completion_notes' => null,
                        'completion_images' => null,
                        'rating' => null,
                        'feedback' => null,
                        'created_at' => null,
                        'updated_at' => null,
                        'parentService' => $svc->parentService ? [
                            'id' => $svc->parentService->id,
                            'name' => $svc->parentService->name,
                            'description' => $svc->parentService->description,
                            'image' => $svc->parentService->image,
                        ] : null,
                    ];
                }),
            ];
        });
        $orders->setCollection($collection);

        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    public function show(string $workOrderNumber, Request $request)
    {
        $customer = $request->user();
        $workOrder = WorkOrder::with([
            'services.parentService',
            'status',
            'subStatus',
            'address',
        ])->where('customer_id', $customer->id)
            ->where('work_order_number', $workOrderNumber)
            ->first();

        if (!$workOrder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Work order not found',
            ], 404);
        }

        $statusSlug = $workOrder->status?->slug ?? null;
        $subStatusSlug = $workOrder->subStatus?->slug ?? null;
        $mappedStatus = 'in_progress';
        if ($statusSlug === 'completed') {
            $mappedStatus = 'completed';
        } elseif ($statusSlug === 'cancelled') {
            $mappedStatus = 'cancelled';
        } elseif ($statusSlug === 'allocated' || $statusSlug === 'scheduled' || $subStatusSlug === 'just-launched') {
            $mappedStatus = 'pending';
        }

        $order = [
            'id' => $workOrder->id,
            'customer_id' => $workOrder->customer_id,
            'customer_address_id' => $workOrder->customer_address_id,
            'order_number' => $workOrder->work_order_number,
            'order_date' => optional($workOrder->created_at)->toDateTimeString(),
            'notes' => $workOrder->customer_description,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'total_amount' => (float) ($workOrder->total_amount ?? 0),
            'paid_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'status' => $mappedStatus,
            'confirmed_at' => null,
            'completed_at' => optional($workOrder->completed_at)->toDateTimeString(),
            'cancelled_at' => optional($workOrder->cancelled_at)->toDateTimeString(),
            'created_at' => optional($workOrder->created_at)->toDateTimeString(),
            'updated_at' => optional($workOrder->updated_at)->toDateTimeString(),
            'address' => $workOrder->address ? [
                'address_type' => $workOrder->address->address_type ?? 'home',
                'address_line_1' => $workOrder->address->address_line_1 ?? '',
                'city' => $workOrder->address->city ?? '',
                'state' => $workOrder->address->state ?? '',
                'zip_code' => $workOrder->address->zip_code ?? '',
            ] : null,
            'items' => $workOrder->services->map(function ($svc) use ($workOrder) {
                return [
                    'id' => $svc->id,
                    'order_id' => $svc->work_order_id,
                    'parent_service_id' => $svc->parent_service_id,
                    'quantity' => (int) ($svc->quantity ?? 1),
                    'unit_price' => (float) ($svc->base_price ?? 0),
                    'discount' => 0,
                    'discount_type' => 'fixed',
                    'subtotal' => (float) ($svc->final_price ?? $svc->base_price ?? 0),
                    'scheduled_date' => optional($workOrder->appointment_date)?->toDateString(),
                    'scheduled_time' => $workOrder->appointment_time,
                    'status' => 'pending',
                    'assigned_technician_id' => null,
                    'assigned_at' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    'completion_notes' => null,
                    'completion_images' => null,
                    'rating' => null,
                    'feedback' => null,
                    'created_at' => null,
                    'updated_at' => null,
                    'parentService' => $svc->parentService ? [
                        'id' => $svc->parentService->id,
                        'name' => $svc->parentService->name,
                        'description' => $svc->parentService->description,
                        'image' => $svc->parentService->image,
                    ] : null,
                ];
            }),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $order,
        ]);
    }

    public function cancel(string $workOrderNumber, Request $request)
    {
        $customer = $request->user();
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $workOrder = WorkOrder::where('customer_id', $customer->id)
            ->where('work_order_number', $workOrderNumber)
            ->first();

        if (!$workOrder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Work order not found',
            ], 404);
        }

        if ($workOrder->cancelled_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Work order already cancelled',
            ], 400);
        }

        $workOrder->update([
            'cancelled_at' => now(),
            'cancelled_by' => $customer->id,
        ]);

        Notification::createNotification(
            $customer->id,
            'App\Models\Customer',
            'Work Order Cancelled',
            "Your work order #{$workOrder->work_order_number} has been cancelled.",
            'work-order',
            [
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->work_order_number,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Work order cancelled successfully',
        ]);
    }
}
