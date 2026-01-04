<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $today = Carbon::today();

            // CSO Monthly KPIs - Based on work orders CLOSED BY this user
            $monthlyTarget = 100; // This could come from user settings/targets table
            $monthlyCompleted = WorkOrder::where('closed_by', $userId)
                ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                ->count();

            $todayCompleted = WorkOrder::where('closed_by', $userId)
                ->whereDate('completed_at', $today)
                ->count();

            // Average completion time for work orders closed by this user (in hours)
            $avgCompletionTime = WorkOrder::where('closed_by', $userId)
                ->whereNotNull('completed_at')
                ->whereNotNull('created_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours')
                ->value('avg_hours') ?? 0;

            // Customer rating for work orders closed by this user
            $customerRating = WorkOrder::where('closed_by', $userId)
                ->whereNotNull('customer_rating')
                ->avg('customer_rating') ?? 0;

            // On-time completion rate for work orders closed by this user
            $totalCompleted = WorkOrder::where('closed_by', $userId)
                ->whereNotNull('completed_at')
                ->count();

            $onTimeCompleted = WorkOrder::where('closed_by', $userId)
                ->whereNotNull('completed_at')
                ->whereNotNull('appointment_date')
                ->whereRaw('completed_at <= appointment_date')
                ->count();

            $onTimeRate = $totalCompleted > 0 ? round(($onTimeCompleted / $totalCompleted) * 100) : 0;

            // Overall work order status counts (all work orders in system)
            $workOrderStatus = [
                'assigned' => WorkOrder::whereNull('completed_at')
                    ->whereNull('cancelled_at')
                    ->whereNotNull('assigned_to_id')
                    ->count(),
                'inProgress' => WorkOrder::where('status_id', function($query) {
                        $query->select('id')
                            ->from('work_order_statuses')
                            ->where('name', 'In Progress')
                            ->limit(1);
                    })
                    ->count(),
                'completed' => WorkOrder::whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                    ->count(),
                'overdue' => WorkOrder::whereNull('completed_at')
                    ->whereNotNull('appointment_date')
                    ->where('appointment_date', '<', now())
                    ->count(),
            ];

            // Daily completion for the week (work orders closed by this user)
            $dailyCompletion = [];
            $startOfWeek = Carbon::now()->startOfWeek();
            
            for ($i = 0; $i < 7; $i++) {
                $date = $startOfWeek->copy()->addDays($i);
                $completed = WorkOrder::where('closed_by', $userId)
                    ->whereDate('completed_at', $date)
                    ->count();
                
                $dailyCompletion[] = [
                    'day' => $date->format('D'),
                    'completed' => $completed,
                    'target' => 5, // Daily target, could be configurable
                ];
            }

            // Work order aging (overall system work orders that are not completed)
            $agingData = [
                [
                    'age' => 'Today',
                    'count' => WorkOrder::whereNull('completed_at')
                        ->whereDate('created_at', $today)
                        ->count(),
                    'color' => '#10b981',
                ],
                [
                    'age' => '1-2 Days',
                    'count' => WorkOrder::whereNull('completed_at')
                        ->whereBetween('created_at', [
                            $today->copy()->subDays(2),
                            $today->copy()->subDay()
                        ])
                        ->count(),
                    'color' => '#3b82f6',
                ],
                [
                    'age' => '3-5 Days',
                    'count' => WorkOrder::whereNull('completed_at')
                        ->whereBetween('created_at', [
                            $today->copy()->subDays(5),
                            $today->copy()->subDays(3)
                        ])
                        ->count(),
                    'color' => '#f59e0b',
                ],
                [
                    'age' => '6+ Days',
                    'count' => WorkOrder::whereNull('completed_at')
                        ->where('created_at', '<', $today->copy()->subDays(6))
                        ->count(),
                    'color' => '#ef4444',
                ],
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'kpis' => [
                        'monthlyTarget' => $monthlyTarget,
                        'completed' => $monthlyCompleted,
                        'todayCompleted' => $todayCompleted,
                        'avgCompletionTime' => round($avgCompletionTime, 1),
                        'customerRating' => round($customerRating, 1),
                        'onTimeCompletion' => $onTimeRate,
                    ],
                    'workOrderStatus' => $workOrderStatus,
                    'dailyCompletion' => $dailyCompletion,
                    'agingData' => $agingData,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch dashboard data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get admin dashboard statistics (for managers/admins)
     */
    public function adminDashboard(Request $request): JsonResponse
    {
        try {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();

            // Overall statistics
            $totalWorkOrders = WorkOrder::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
            $completedWorkOrders = WorkOrder::whereBetween('completed_at', [$startOfMonth, $endOfMonth])->count();
            $pendingWorkOrders = WorkOrder::whereNull('completed_at')->whereNull('cancelled_at')->count();
            $overdueWorkOrders = WorkOrder::whereNull('completed_at')
                ->whereNotNull('scheduled_date')
                ->where('scheduled_date', '<', now())
                ->count();

            // Revenue statistics (if you have amount fields)
            $monthlyRevenue = WorkOrder::whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                ->sum('final_amount') ?? 0;

            // Status distribution
            $statusDistribution = DB::table('work_orders')
                ->join('work_order_statuses', 'work_orders.status_id', '=', 'work_order_statuses.id')
                ->select('work_order_statuses.name as status', DB::raw('count(*) as count'))
                ->whereNull('work_orders.deleted_at')
                ->groupBy('work_order_statuses.name')
                ->get();

            // Top performing CSOs
            $topPerformers = DB::table('work_orders')
                ->join('staff', 'work_orders.assigned_to', '=', 'staff.id')
                ->select(
                    'staff.id',
                    DB::raw("CONCAT(staff.first_name, ' ', staff.last_name) as name"),
                    DB::raw('count(*) as completed_count')
                )
                ->whereBetween('work_orders.completed_at', [$startOfMonth, $endOfMonth])
                ->groupBy('staff.id', 'staff.first_name', 'staff.last_name')
                ->orderByDesc('completed_count')
                ->limit(5)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'overview' => [
                        'totalWorkOrders' => $totalWorkOrders,
                        'completedWorkOrders' => $completedWorkOrders,
                        'pendingWorkOrders' => $pendingWorkOrders,
                        'overdueWorkOrders' => $overdueWorkOrders,
                        'monthlyRevenue' => $monthlyRevenue,
                    ],
                    'statusDistribution' => $statusDistribution,
                    'topPerformers' => $topPerformers,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch admin dashboard data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get work order analytics data
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            // Get filter parameters
            $categoryId = $request->get('category_id');
            $serviceId = $request->get('service_id');
            $parentServiceId = $request->get('parent_service_id');
            $statusId = $request->get('status_id');
            $subStatusId = $request->get('sub_status_id');

            // Base query for open work orders
            $baseQuery = WorkOrder::whereNull('completed_at')
                ->whereNull('cancelled_at');

            // Apply filters if provided
            if ($categoryId) {
                $baseQuery->where('category_id', $categoryId);
            }
            if ($serviceId) {
                $baseQuery->where('service_id', $serviceId);
            }
            if ($parentServiceId) {
                $baseQuery->where('parent_service_id', $parentServiceId);
            }
            if ($statusId) {
                $baseQuery->where('status_id', $statusId);
            }
            if ($subStatusId) {
                $baseQuery->where('sub_status_id', $subStatusId);
            }

            // Get counts by category
            $byCategory = DB::table('work_orders')
                ->join('categories', 'work_orders.category_id', '=', 'categories.id')
                ->select(
                    'categories.id',
                    'categories.name',
                    DB::raw('count(*) as count')
                )
                ->whereNull('work_orders.completed_at')
                ->whereNull('work_orders.cancelled_at')
                ->when($serviceId, fn($q) => $q->where('work_orders.service_id', $serviceId))
                ->when($parentServiceId, fn($q) => $q->where('work_orders.parent_service_id', $parentServiceId))
                ->when($statusId, fn($q) => $q->where('work_orders.status_id', $statusId))
                ->when($subStatusId, fn($q) => $q->where('work_orders.sub_status_id', $subStatusId))
                ->groupBy('categories.id', 'categories.name')
                ->orderByDesc('count')
                ->get();

            // Get counts by service
            $byService = DB::table('work_orders')
                ->join('services', 'work_orders.service_id', '=', 'services.id')
                ->select(
                    'services.id',
                    'services.name',
                    DB::raw('count(*) as count')
                )
                ->whereNull('work_orders.completed_at')
                ->whereNull('work_orders.cancelled_at')
                ->when($categoryId, fn($q) => $q->where('work_orders.category_id', $categoryId))
                ->when($parentServiceId, fn($q) => $q->where('work_orders.parent_service_id', $parentServiceId))
                ->when($statusId, fn($q) => $q->where('work_orders.status_id', $statusId))
                ->when($subStatusId, fn($q) => $q->where('work_orders.sub_status_id', $subStatusId))
                ->groupBy('services.id', 'services.name')
                ->orderByDesc('count')
                ->get();

            // Get counts by parent service
            $byParentService = DB::table('work_orders')
                ->join('parent_services', 'work_orders.parent_service_id', '=', 'parent_services.id')
                ->select(
                    'parent_services.id',
                    'parent_services.name',
                    DB::raw('count(*) as count')
                )
                ->whereNull('work_orders.completed_at')
                ->whereNull('work_orders.cancelled_at')
                ->when($categoryId, fn($q) => $q->where('work_orders.category_id', $categoryId))
                ->when($serviceId, fn($q) => $q->where('work_orders.service_id', $serviceId))
                ->when($statusId, fn($q) => $q->where('work_orders.status_id', $statusId))
                ->when($subStatusId, fn($q) => $q->where('work_orders.sub_status_id', $subStatusId))
                ->groupBy('parent_services.id', 'parent_services.name')
                ->orderByDesc('count')
                ->get();

            // Get counts by status
            $byStatus = DB::table('work_orders')
                ->join('work_order_statuses', 'work_orders.status_id', '=', 'work_order_statuses.id')
                ->select(
                    'work_order_statuses.id',
                    'work_order_statuses.name',
                    DB::raw('count(*) as count')
                )
                ->whereNull('work_orders.completed_at')
                ->whereNull('work_orders.cancelled_at')
                ->when($categoryId, fn($q) => $q->where('work_orders.category_id', $categoryId))
                ->when($serviceId, fn($q) => $q->where('work_orders.service_id', $serviceId))
                ->when($parentServiceId, fn($q) => $q->where('work_orders.parent_service_id', $parentServiceId))
                ->when($subStatusId, fn($q) => $q->where('work_orders.sub_status_id', $subStatusId))
                ->groupBy('work_order_statuses.id', 'work_order_statuses.name')
                ->orderByDesc('count')
                ->get();

            // Get counts by sub-status
            $bySubStatus = DB::table('work_orders')
                ->join('work_order_sub_statuses', 'work_orders.sub_status_id', '=', 'work_order_sub_statuses.id')
                ->select(
                    'work_order_sub_statuses.id',
                    'work_order_sub_statuses.name',
                    DB::raw('count(*) as count')
                )
                ->whereNull('work_orders.completed_at')
                ->whereNull('work_orders.cancelled_at')
                ->when($categoryId, fn($q) => $q->where('work_orders.category_id', $categoryId))
                ->when($serviceId, fn($q) => $q->where('work_orders.service_id', $serviceId))
                ->when($parentServiceId, fn($q) => $q->where('work_orders.parent_service_id', $parentServiceId))
                ->when($statusId, fn($q) => $q->where('work_orders.status_id', $statusId))
                ->groupBy('work_order_sub_statuses.id', 'work_order_sub_statuses.name')
                ->orderByDesc('count')
                ->get();

            // Get total count
            $totalCount = $baseQuery->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total' => $totalCount,
                    'byCategory' => $byCategory,
                    'byService' => $byService,
                    'byParentService' => $byParentService,
                    'byStatus' => $byStatus,
                    'bySubStatus' => $bySubStatus,
                    'filters' => [
                        'category_id' => $categoryId,
                        'service_id' => $serviceId,
                        'parent_service_id' => $parentServiceId,
                        'status_id' => $statusId,
                        'sub_status_id' => $subStatusId,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch analytics data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
