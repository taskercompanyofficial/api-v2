<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Staff;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryController extends Controller
{
    use QueryFilterTrait;

    /**
     * Get salary calculations for staff based on attendance
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10);
        $month = $request->input('month', now()->format('Y-m'));
        $branchId = $request->input('branch_id');

        // Parse month to get start and end dates
        $startDate = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $endDate = \Carbon\Carbon::parse($month . '-01')->endOfMonth();
        $totalDaysInMonth = $startDate->daysInMonth;

        // Query staff with their attendance for the month
        $query = Staff::query()
            ->select([
                'staff.id',
                'staff.first_name',
                'staff.middle_name',
                'staff.last_name',
                'staff.code',
                'staff.salary_payout',
                'staff.branch_id',
                'staff.joining_date',
            ])
            ->with(['branch:id,name', 'attendances' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            }])
            ->whereNotNull('salary_payout')
            ->where('salary_payout', '>', 0);

        // Filter by branch if provided
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Apply other filters
        if ($request->has('filters')) {
            $filters = is_array($request->input('filters'))
                ? $request->input('filters')
                : json_decode($request->input('filters'), true);

            if (is_array($filters)) {
                foreach ($filters as &$filter) {
                    if (isset($filter['id']) && $filter['id'] === 'name') {
                        $filter['id'] = 'first_name';
                    }
                }
                $request->merge(['filters' => $filters]);
            }
        }
        $this->applyJsonFilters($query, $request);

        // Custom sorting mapping
        if ($request->has('sort')) {
            $sortRules = json_decode($request->input('sort'), true);
            if (is_array($sortRules)) {
                foreach ($sortRules as &$rule) {
                    if ($rule['id'] === 'monthly_salary') {
                        $rule['id'] = 'salary_payout';
                    }
                    if ($rule['id'] === 'name') {
                        $rule['id'] = 'first_name';
                    }
                }
                $request->merge(['sort' => json_encode($sortRules)]);
            }
        }
        $this->applySorting($query, $request);

        $staff = $query->paginate($perPage, ['*'], 'page', $page);

        // Calculate salary for each staff member
        $data = $staff->map(function ($staffMember) use ($startDate, $endDate, $totalDaysInMonth) {
            // Get attendance records for the month (already eager loaded)
            $attendances = $staffMember->attendances;

            // Count different attendance statuses
            $presentDays = $attendances->whereIn('status', ['present', 'late'])->count();
            $halfDays = $attendances->where('status', 'half_day')->count();
            $absentDays = $totalDaysInMonth - ($presentDays + $halfDays);

            // Calculate effective working days (half day = 0.5 day)
            $effectiveWorkingDays = $presentDays + ($halfDays * 0.5);

            // Calculate daily rate
            $monthlySalary = (float) $staffMember->salary_payout;
            $dailyRate = $monthlySalary / $totalDaysInMonth;

            // Calculate payable salary
            $payableSalary = $dailyRate * $effectiveWorkingDays;

            // Calculate total working hours
            $totalWorkingHours = $attendances->sum('working_hours');

            return [
                'id' => $staffMember->id,
                'code' => $staffMember->code,
                'name' => trim($staffMember->first_name . ' ' . ($staffMember->middle_name ?? '') . ' ' . $staffMember->last_name),
                'branch' => $staffMember->branch ? $staffMember->branch->name : null,
                'branch_id' => $staffMember->branch_id,
                'monthly_salary' => number_format($monthlySalary, 2),
                'daily_rate' => number_format($dailyRate, 2),
                'total_days' => $totalDaysInMonth,
                'present_days' => $presentDays,
                'half_days' => $halfDays,
                'absent_days' => $absentDays,
                'effective_working_days' => round($effectiveWorkingDays, 2),
                'total_working_hours' => round($totalWorkingHours, 2),
                'payable_salary' => number_format($payableSalary, 2),
                'deduction' => number_format($monthlySalary - $payableSalary, 2),
                'attendance_percentage' => $totalDaysInMonth > 0 ? round(($effectiveWorkingDays / $totalDaysInMonth) * 100, 1) : 0,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $staff->currentPage(),
                'last_page' => $staff->lastPage(),
                'per_page' => $staff->perPage(),
                'total' => $staff->total(),
            ],
            'summary' => [
                'month' => $startDate->format('F Y'),
                'total_days' => $totalDaysInMonth,
                'total_staff' => $staff->total(),
            ],
        ]);
    }

    /**
     * Get salary details for a specific staff member
     */
    public function show(Request $request, string $staffId)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $startDate = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $endDate = \Carbon\Carbon::parse($month . '-01')->endOfMonth();
        $totalDaysInMonth = $startDate->daysInMonth;

        $staff = Staff::with(['branch:id,name', 'staffRole:id,name'])
            ->findOrFail($staffId);

        if (!$staff->salary_payout || $staff->salary_payout <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No salary configured for this staff member',
            ], 404);
        }

        // Get all attendance records for the month
        $attendances = Attendance::where('staff_id', $staff->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get();

        // Count different attendance statuses
        $presentDays = $attendances->whereIn('status', ['present', 'late'])->count();
        $halfDays = $attendances->where('status', 'half_day')->count();
        $lateDays = $attendances->where('status', 'late')->count();
        $absentDays = $totalDaysInMonth - ($presentDays + $halfDays);

        // Calculate effective working days
        $effectiveWorkingDays = $presentDays + ($halfDays * 0.5);

        // Calculate salary
        $monthlySalary = (float) $staff->salary_payout;
        $dailyRate = $monthlySalary / $totalDaysInMonth;
        $payableSalary = $dailyRate * $effectiveWorkingDays;
        $deduction = $monthlySalary - $payableSalary;

        // Calculate total working hours
        $totalWorkingHours = $attendances->sum('working_hours');
        $avgWorkingHours = $attendances->where('working_hours', '>', 0)->avg('working_hours') ?? 0;

        // Prepare daily breakdown
        $dailyBreakdown = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $attendance = $attendances->where('date', $currentDate->format('Y-m-d'))->first();
            $dailyBreakdown[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day' => $currentDate->format('l'),
                'status' => $attendance ? $attendance->status : 'absent',
                'check_in_time' => $attendance ? $attendance->check_in_time : null,
                'check_out_time' => $attendance ? $attendance->check_out_time : null,
                'working_hours' => $attendance ? $attendance->working_hours : 0,
            ];
            $currentDate->addDay();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'staff' => [
                    'id' => $staff->id,
                    'code' => $staff->code,
                    'name' => trim($staff->first_name . ' ' . ($staff->middle_name ?? '') . ' ' . $staff->last_name),
                    'branch' => $staff->branch ? $staff->branch->name : null,
                    'designation' => $staff->staffRole ? $staff->staffRole->name : null,
                    'joining_date' => $staff->joining_date,
                ],
                'salary_info' => [
                    'month' => $startDate->format('F Y'),
                    'monthly_salary' => number_format($monthlySalary, 2),
                    'daily_rate' => number_format($dailyRate, 2),
                    'payable_salary' => number_format($payableSalary, 2),
                    'deduction' => number_format($deduction, 2),
                ],
                'attendance_summary' => [
                    'total_days' => $totalDaysInMonth,
                    'present_days' => $presentDays,
                    'half_days' => $halfDays,
                    'late_days' => $lateDays,
                    'absent_days' => $absentDays,
                    'effective_working_days' => round($effectiveWorkingDays, 2),
                    'attendance_percentage' => round(($effectiveWorkingDays / $totalDaysInMonth) * 100, 1),
                ],
                'working_hours' => [
                    'total_hours' => round($totalWorkingHours, 2),
                    'average_hours' => round($avgWorkingHours, 2),
                ],
                'daily_breakdown' => $dailyBreakdown,
            ],
        ]);
    }
}
