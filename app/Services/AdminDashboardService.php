<?php

namespace App\Services;

use App\Models\WorkOrder;
use App\Models\Staff;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\OurBranch;
use App\Models\LeaveApplication;
use App\Models\Part;
use App\Models\WorkOrderFile;
use App\Models\Categories;
use App\Models\Service;
use Nnjeim\World\Models\City;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    protected ?int $branchId = null;
    protected ?string $city = null;
    protected ?int $categoryId = null;
    protected ?int $serviceId = null;
    protected Carbon $date;
    protected Carbon $startOfMonth;
    protected Carbon $endOfMonth;
    protected Carbon $startOfWeek;
    protected Carbon $endOfWeek;
    protected Carbon $today;

    /**
     * Initialize date ranges
     */
    public function __construct()
    {
        $this->date = Carbon::today();
        $this->startOfMonth = Carbon::now()->startOfMonth();
        $this->endOfMonth = Carbon::now()->endOfMonth();
        $this->startOfWeek = Carbon::now()->startOfWeek();
        $this->endOfWeek = Carbon::now()->endOfWeek();
        $this->today = Carbon::today();
    }

    /**
     * Set filters
     */
    public function setFilters(
        ?int $branchId = null,
        ?string $city = null,
        ?Carbon $date = null,
        ?int $categoryId = null,
        ?int $serviceId = null
    ): self {
        $this->branchId = $branchId;
        $this->city = $city;
        $this->categoryId = $categoryId;
        $this->serviceId = $serviceId;
        if ($date) {
            $this->date = $date;
        }
        return $this;
    }

    /**
     * Get base query with filters applied
     */
    protected function baseWorkOrderQuery()
    {
        return WorkOrder::query()
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->when($this->city, fn($q) => $q->whereHas('city', fn($cq) => $cq->where('name', $this->city)))
            ->when($this->categoryId, fn($q) => $q->where('category_id', $this->categoryId))
            ->when($this->serviceId, fn($q) => $q->where('service_id', $this->serviceId));
    }

    /**
     * Get overview statistics
     */
    public function getOverviewStats(): array
    {
        $baseQuery = $this->baseWorkOrderQuery();

        return [
            'totalWorkOrders' => (clone $baseQuery)->whereBetween('created_at', [$this->startOfMonth, $this->endOfMonth])->count(),
            'completedWorkOrders' => (clone $baseQuery)->whereBetween('completed_at', [$this->startOfMonth, $this->endOfMonth])->count(),
            'pendingWorkOrders' => (clone $baseQuery)->whereNull('completed_at')->whereNull('cancelled_at')->count(),
            'overdueWorkOrders' => (clone $baseQuery)
                ->whereNull('completed_at')
                ->whereNotNull('appointment_date')
                ->where('appointment_date', '<', now())
                ->count(),
            'monthlyRevenue' => (clone $baseQuery)
                ->whereBetween('completed_at', [$this->startOfMonth, $this->endOfMonth])
                ->sum('final_amount') ?? 0,
            'newCustomers' => Customer::whereBetween('created_at', [$this->startOfMonth, $this->endOfMonth])
                ->count(),
            'activeDealers' => Dealer::where('status', 'active')->count(),
        ];
    }

    /**
     * Get worker KPIs (completion rates)
     */
    public function getWorkerKPIs(): array
    {
        $baseQuery = $this->baseWorkOrderQuery();

        // Today
        $todayCreated = (clone $baseQuery)->whereDate('created_at', $this->today)->count();
        $todayClosed = (clone $baseQuery)->whereDate('completed_at', $this->today)->count();
        $todayRate = $todayCreated > 0 ? round(($todayClosed / $todayCreated) * 100, 1) : 0;

        // This week
        $weekCreated = (clone $baseQuery)->whereBetween('created_at', [$this->startOfWeek, $this->endOfWeek])->count();
        $weekClosed = (clone $baseQuery)->whereBetween('completed_at', [$this->startOfWeek, $this->endOfWeek])->count();
        $weekRate = $weekCreated > 0 ? round(($weekClosed / $weekCreated) * 100, 1) : 0;

        // This month
        $monthCreated = (clone $baseQuery)->whereBetween('created_at', [$this->startOfMonth, $this->endOfMonth])->count();
        $monthClosed = (clone $baseQuery)->whereBetween('completed_at', [$this->startOfMonth, $this->endOfMonth])->count();
        $monthRate = $monthCreated > 0 ? round(($monthClosed / $monthCreated) * 100, 1) : 0;

        return [
            'today' => ['created' => $todayCreated, 'closed' => $todayClosed, 'rate' => $todayRate],
            'thisWeek' => ['created' => $weekCreated, 'closed' => $weekClosed, 'rate' => $weekRate],
            'thisMonth' => ['created' => $monthCreated, 'closed' => $monthClosed, 'rate' => $monthRate],
        ];
    }

    /**
     * Get completion time distribution
     */
    public function getCompletionTimeDistribution(): array
    {
        $completedWOs = $this->baseWorkOrderQuery()
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$this->startOfMonth, $this->endOfMonth])
            ->selectRaw('DATEDIFF(completed_at, created_at) as days_to_complete')
            ->get();

        $totalCompleted = $completedWOs->count();
        $distribution = [
            ['bucket' => '1 Day', 'count' => 0, 'percentage' => 0],
            ['bucket' => '2 Days', 'count' => 0, 'percentage' => 0],
            ['bucket' => '3-4 Days', 'count' => 0, 'percentage' => 0],
            ['bucket' => '5-6 Days', 'count' => 0, 'percentage' => 0],
            ['bucket' => '8+ Days', 'count' => 0, 'percentage' => 0],
        ];

        foreach ($completedWOs as $wo) {
            $days = $wo->days_to_complete;
            if ($days <= 1) {
                $distribution[0]['count']++;
            } elseif ($days == 2) {
                $distribution[1]['count']++;
            } elseif ($days <= 4) {
                $distribution[2]['count']++;
            } elseif ($days <= 6) {
                $distribution[3]['count']++;
            } else {
                $distribution[4]['count']++;
            }
        }

        // Calculate percentages
        foreach ($distribution as &$bucket) {
            $bucket['percentage'] = $totalCompleted > 0
                ? round(($bucket['count'] / $totalCompleted) * 100, 1)
                : 0;
        }

        return $distribution;
    }

    /**
     * Get completion target stats
     */
    public function getCompletionTarget(): array
    {
        $distribution = $this->getCompletionTimeDistribution();
        $totalCompleted = array_sum(array_column($distribution, 'count'));
        $within2Days = $distribution[0]['count'] + $distribution[1]['count'];

        return [
            'targetDays' => 2,
            'targetPercentage' => 80,
            'currentPercentage' => $totalCompleted > 0 ? round(($within2Days / $totalCompleted) * 100, 1) : 0,
        ];
    }

    /**
     * Get staff workload
     */
    public function getStaffWorkload(): array
    {
        return Staff::select('staff.id as staffId')
            ->selectRaw("CONCAT(staff.first_name, ' ', staff.last_name) as name")
            ->selectRaw("SUM(CASE WHEN work_orders.completed_at IS NULL AND work_orders.cancelled_at IS NULL THEN 1 ELSE 0 END) as open")
            ->selectRaw("SUM(CASE WHEN DATE(work_orders.completed_at) = CURDATE() THEN 1 ELSE 0 END) as closed")
            ->join('work_orders', 'work_orders.assigned_to_id', '=', 'staff.id')
            ->whereDate('work_orders.created_at', $this->date)
            ->when($this->branchId, fn($q) => $q->where('work_orders.branch_id', $this->branchId))
            ->whereNull('work_orders.deleted_at')
            ->groupBy('staff.id', 'staff.first_name', 'staff.last_name')
            ->get()
            ->map(function ($staff) {
                $total = $staff->open + $staff->closed;
                $staff->rate = $total > 0 ? round(($staff->closed / $total) * 100, 1) : 0;
                return $staff;
            })
            ->toArray();
    }

    /**
     * Get weekly KPI trend
     */
    public function getWeeklyKpiTrend(): array
    {
        $trend = [];
        $startOfLast7Days = Carbon::now()->subDays(6)->startOfDay();
        $baseQuery = $this->baseWorkOrderQuery();

        for ($i = 0; $i < 7; $i++) {
            $dayDate = $startOfLast7Days->copy()->addDays($i);
            $received = (clone $baseQuery)->whereDate('created_at', $dayDate)->count();
            $closed = (clone $baseQuery)->whereDate('completed_at', $dayDate)->count();
            $kpi = $received > 0 ? round(($closed / $received) * 100) : 0;

            $trend[] = [
                'day' => $dayDate->format('D'),
                'date' => $dayDate->format('Y-m-d'),
                'received' => $received,
                'closed' => $closed,
                'kpi' => $kpi,
            ];
        }

        return $trend;
    }

    /**
     * Get weekly KPI summary
     */
    public function getWeeklyKpiSummary(): array
    {
        $trend = $this->getWeeklyKpiTrend();
        $kpiValues = array_column($trend, 'kpi');
        $averageKpi = count($kpiValues) > 0 ? round(array_sum($kpiValues) / count($kpiValues)) : 0;
        $bestDayIndex = array_search(max($kpiValues), $kpiValues);
        $worstDayIndex = array_search(min($kpiValues), $kpiValues);

        return [
            'average' => $averageKpi,
            'bestDay' => [
                'day' => $trend[$bestDayIndex]['day'] ?? '',
                'kpi' => $kpiValues[$bestDayIndex] ?? 0,
            ],
            'worstDay' => [
                'day' => $trend[$worstDayIndex]['day'] ?? '',
                'kpi' => $kpiValues[$worstDayIndex] ?? 0,
            ],
            'target' => 85,
        ];
    }

    /**
     * Get revenue overview
     */
    public function getRevenueOverview(): array
    {
        $baseQuery = $this->baseWorkOrderQuery();

        return [
            'collected' => (clone $baseQuery)
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$this->startOfMonth, $this->endOfMonth])
                ->sum('final_amount') ?? 0,
            'pending' => (clone $baseQuery)
                ->whereNull('completed_at')
                ->whereNull('cancelled_at')
                ->sum('final_amount') ?? 0,
            'overdue' => (clone $baseQuery)
                ->whereNull('completed_at')
                ->whereNotNull('appointment_date')
                ->where('appointment_date', '<', now())
                ->sum('final_amount') ?? 0,
        ];
    }

    /**
     * Get work order pipeline by status
     */
    public function getWorkOrderPipeline(): array
    {
        return WorkOrder::select('work_order_statuses.name as status')
            ->selectRaw('count(*) as count')
            ->join('work_order_statuses', 'work_orders.status_id', '=', 'work_order_statuses.id')
            ->whereNull('work_orders.deleted_at')
            ->whereNull('work_orders.completed_at')
            ->whereNull('work_orders.cancelled_at')
            ->when($this->branchId, fn($q) => $q->where('work_orders.branch_id', $this->branchId))
            ->groupBy('work_order_statuses.name')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    /**
     * Get top performers
     */
    public function getTopPerformers(int $limit = 5): array
    {
        return Staff::select('staff.id')
            ->selectRaw("CONCAT(staff.first_name, ' ', staff.last_name) as name")
            ->selectRaw('count(*) as completed_count')
            ->join('work_orders', 'work_orders.closed_by', '=', 'staff.id')
            ->whereBetween('work_orders.completed_at', [$this->startOfMonth, $this->endOfMonth])
            ->when($this->branchId, fn($q) => $q->where('work_orders.branch_id', $this->branchId))
            ->groupBy('staff.id', 'staff.first_name', 'staff.last_name')
            ->orderByDesc('completed_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get attendance summary
     */
    public function getAttendanceSummary(): array
    {
        return [
            'present' => Attendance::whereDate('check_in_time', $this->today)
                ->whereNotNull('check_in_time')
                ->count(),
            'late' => Attendance::whereDate('check_in_time', $this->today)
                ->where('status', 'late')
                ->count(),
            'absent' => Staff::where('status_id', function ($query) {
                $query->select('id')->from('statuses')->where('name', 'Active')->limit(1);
            })
                ->whereNotIn('id', function ($query) {
                    $query->select('staff_id')
                        ->from('attendances')
                        ->whereDate('check_in_time', $this->today);
                })
                ->count(),
            'onLeave' => LeaveApplication::where('status', 'approved')
                ->whereDate('start_date', '<=', $this->today)
                ->whereDate('end_date', '>=', $this->today)
                ->count(),
        ];
    }

    /**
     * Get pending actions
     */
    public function getPendingActions(): array
    {
        return [
            'fileApprovals' => WorkOrderFile::where('approval_status', 'pending')->count(),
            'partsInDemand' => Part::where('status', 'in_demand')->count(),
            'leaveRequests' => LeaveApplication::where('status', 'pending')->count(),
            'unreadMessages' => 0, // Placeholder for WhatsApp integration
        ];
    }

    /**
     * Get distribution by city (using city_id relationship)
     */
    public function getDistributionByCity(int $limit = 10): array
    {
        return WorkOrder::select('cities.name as city')
            ->selectRaw('count(*) as count')
            ->join('cities', 'work_orders.city_id', '=', 'cities.id')
            ->whereNull('work_orders.completed_at')
            ->whereNull('work_orders.cancelled_at')
            ->whereNull('work_orders.deleted_at')
            ->when($this->branchId, fn($q) => $q->where('work_orders.branch_id', $this->branchId))
            ->groupBy('cities.name')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get distribution by branch
     */
    public function getDistributionByBranch(): array
    {
        return WorkOrder::select('our_branches.name as branch')
            ->selectRaw('count(*) as count')
            ->join('our_branches', 'work_orders.branch_id', '=', 'our_branches.id')
            ->whereNull('work_orders.completed_at')
            ->whereNull('work_orders.cancelled_at')
            ->whereNull('work_orders.deleted_at')
            ->groupBy('our_branches.name')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    /**
     * Get filter options
     */
    public function getFilterOptions(): array
    {
        return [
            'branches' => OurBranch::select('id', 'name')
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->toArray(),
            'cities' => City::select('id', 'name')
                ->whereIn('id', WorkOrder::select('city_id')->distinct())
                ->orderBy('name')
                ->get()
                ->toArray(),
            'categories' => Categories::select('id', 'name')
                ->whereIn('id', WorkOrder::select('category_id')->distinct())
                ->orderBy('name')
                ->get()
                ->toArray(),
            'services' => Service::select('id', 'name', 'category_id')
                ->whereIn('id', WorkOrder::select('service_id')->distinct())
                ->orderBy('name')
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Get all dashboard data
     */
    public function getAllData(): array
    {
        $weeklyTrend = $this->getWeeklyKpiTrend();

        return [
            'overview' => $this->getOverviewStats(),
            'workerKPIs' => $this->getWorkerKPIs(),
            'completionTimeDistribution' => $this->getCompletionTimeDistribution(),
            'completionTarget' => $this->getCompletionTarget(),
            'staffWorkload' => $this->getStaffWorkload(),
            'weeklyKpiTrend' => $weeklyTrend,
            'weeklyKpiSummary' => $this->getWeeklyKpiSummary(),
            'revenue' => $this->getRevenueOverview(),
            'workOrderPipeline' => $this->getWorkOrderPipeline(),
            'topPerformers' => $this->getTopPerformers(),
            'attendancesSummary' => $this->getAttendanceSummary(),
            'pendingActions' => $this->getPendingActions(),
            'byCity' => $this->getDistributionByCity(),
            'byBranch' => $this->getDistributionByBranch(),
            'filterOptions' => $this->getFilterOptions(),
            'appliedFilters' => [
                'branch_id' => $this->branchId,
                'city' => $this->city,
                'date' => $this->date->format('Y-m-d'),
            ],
        ];
    }
}
