<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ExpenseCategory;
use App\Models\Staff;
use App\Models\StaffAllowance;
use App\Models\StaffWeeklyExpense;
use App\Models\WeeklyExpenseSummary;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseManagementController extends Controller
{
    /**
     * Get all expense categories
     */
    public function getCategories(): JsonResponse
    {
        $categories = ExpenseCategory::where('is_active', true)->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Create or update expense category
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $slug = \Illuminate\Support\Str::slug($request->name);

        $category = ExpenseCategory::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => true,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Category saved successfully',
            'data' => $category,
        ]);
    }

    /**
     * Get all staff allowances
     */
    public function getAllowances(Request $request): JsonResponse
    {
        $query = StaffAllowance::with([
            'staff:id,first_name,last_name,code,branch_id,has_access_in_crm,salary_payout',
            'staff.branch:id,name',
            'expenseCategory:id,name,slug',
        ]);

        if ($request->has('category_id') && $request->category_id) {
            $query->where('expense_category_id', $request->category_id);
        }

        if ($request->has('branch_id') && $request->branch_id) {
            $query->whereHas('staff', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        $allowances = $query->where('is_active', true)->get();

        return response()->json([
            'status' => 'success',
            'data' => $allowances,
        ]);
    }

    /**
     * Create or update staff allowance
     */
    public function storeAllowance(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount_per_day' => 'required|numeric|min:0',
            'calculation_type' => 'required|in:attendance,weekly,monthly,salary_percentage',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'requires_attendance' => 'boolean',
            'requires_crm_access' => 'boolean',
        ]);

        $user = $request->user();

        $allowance = StaffAllowance::updateOrCreate(
            [
                'staff_id' => $request->staff_id,
                'expense_category_id' => $request->expense_category_id,
            ],
            [
                'amount_per_day' => $request->amount_per_day,
                'calculation_type' => $request->calculation_type,
                'percentage' => $request->percentage,
                'requires_attendance' => $request->requires_attendance ?? true,
                'requires_crm_access' => $request->requires_crm_access ?? false,
                'is_active' => true,
                'updated_by' => $user->id,
            ]
        );

        if (!$allowance->created_by) {
            $allowance->update(['created_by' => $user->id]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Allowance saved successfully',
            'data' => $allowance->load(['staff', 'expenseCategory']),
        ]);
    }

    /**
     * Bulk assign allowance to multiple staff
     */
    public function bulkAssignAllowance(Request $request): JsonResponse
    {
        $request->validate([
            'staff_ids' => 'required|array',
            'staff_ids.*' => 'exists:staff,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount_per_day' => 'required|numeric|min:0',
            'calculation_type' => 'required|in:attendance,weekly,monthly,salary_percentage',
            'requires_attendance' => 'boolean',
            'requires_crm_access' => 'boolean',
        ]);

        $user = $request->user();
        $count = 0;

        foreach ($request->staff_ids as $staffId) {
            StaffAllowance::updateOrCreate(
                [
                    'staff_id' => $staffId,
                    'expense_category_id' => $request->expense_category_id,
                ],
                [
                    'amount_per_day' => $request->amount_per_day,
                    'calculation_type' => $request->calculation_type,
                    'requires_attendance' => $request->requires_attendance ?? true,
                    'requires_crm_access' => $request->requires_crm_access ?? false,
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );
            $count++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Allowance assigned to {$count} staff members",
        ]);
    }

    /**
     * Delete staff allowance
     */
    public function deleteAllowance($id): JsonResponse
    {
        $allowance = StaffAllowance::findOrFail($id);
        $allowance->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Allowance removed successfully',
        ]);
    }

    /**
     * Generate weekly expenses based on attendance
     */
    public function generateWeeklyExpenses(Request $request): JsonResponse
    {
        $request->validate([
            'week_start_date' => 'required|date',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'branch_id' => 'nullable|exists:our_branches,id',
        ]);

        $user = $request->user();
        $weekStart = Carbon::parse($request->week_start_date)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SATURDAY); // Mon-Sat (Sunday off)

        // Get all active allowances for this category
        $query = StaffAllowance::with(['staff'])
            ->where('expense_category_id', $request->expense_category_id)
            ->where('is_active', true);

        if ($request->branch_id) {
            $query->whereHas('staff', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        $allowances = $query->get();
        $generatedCount = 0;
        $totalAmount = 0;
        $totalDays = 0;

        foreach ($allowances as $allowance) {
            $staff = $allowance->staff;

            // Check CRM access requirement
            if ($allowance->requires_crm_access && !$staff->has_access_in_crm) {
                continue;
            }

            // Calculate attendance for the week
            $attendanceData = $this->calculateWeeklyAttendance(
                $staff->id,
                $weekStart,
                $weekEnd,
                $allowance->requires_attendance
            );

            // Calculate amount based on calculation type
            $daysToPayFor = $allowance->requires_attendance
                ? $attendanceData['days_present']
                : $attendanceData['days_expected'];

            $amountPerDay = $allowance->amount_per_day;

            // If salary percentage, calculate from salary
            if ($allowance->calculation_type === 'salary_percentage' && $allowance->percentage) {
                $monthlySalary = $staff->salary_payout ?? 0;
                $dailySalary = $monthlySalary / 26; // Assuming 26 working days per month
                $amountPerDay = ($dailySalary * $allowance->percentage) / 100;
            }

            $totalExpense = $daysToPayFor * $amountPerDay;

            // Create or update the weekly expense record
            StaffWeeklyExpense::updateOrCreate(
                [
                    'staff_id' => $staff->id,
                    'expense_category_id' => $request->expense_category_id,
                    'week_start_date' => $weekStart->format('Y-m-d'),
                ],
                [
                    'staff_allowance_id' => $allowance->id,
                    'week_end_date' => $weekEnd->format('Y-m-d'),
                    'days_expected' => $attendanceData['days_expected'],
                    'days_present' => $attendanceData['days_present'],
                    'days_absent' => $attendanceData['days_absent'],
                    'days_leave' => $attendanceData['days_leave'],
                    'working_days' => $attendanceData['working_days'],
                    'amount_per_day' => $amountPerDay,
                    'total_amount' => $totalExpense,
                    'status' => 'pending',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            $generatedCount++;
            $totalAmount += $totalExpense;
            $totalDays += $daysToPayFor;
        }

        // Update or create summary
        WeeklyExpenseSummary::updateOrCreate(
            [
                'week_start_date' => $weekStart->format('Y-m-d'),
                'expense_category_id' => $request->expense_category_id,
                'branch_id' => $request->branch_id,
            ],
            [
                'week_end_date' => $weekEnd->format('Y-m-d'),
                'total_staff' => $generatedCount,
                'total_amount' => $totalAmount,
                'total_days_paid' => $totalDays,
                'status' => 'generated',
                'generated_by' => $user->id,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => "Generated expenses for {$generatedCount} staff members",
            'data' => [
                'staff_count' => $generatedCount,
                'total_amount' => $totalAmount,
                'total_days' => $totalDays,
            ],
        ]);
    }

    /**
     * Get weekly expenses
     */
    public function getWeeklyExpenses(Request $request): JsonResponse
    {
        $request->validate([
            'week_start_date' => 'required|date',
        ]);

        $weekStart = Carbon::parse($request->week_start_date)->startOfWeek(Carbon::MONDAY);

        $query = StaffWeeklyExpense::with([
            'staff:id,first_name,last_name,code,branch_id,profile_image',
            'staff.branch:id,name',
            'expenseCategory:id,name,slug',
        ])->where('week_start_date', $weekStart->format('Y-m-d'));

        if ($request->has('category_id') && $request->category_id) {
            $query->where('expense_category_id', $request->category_id);
        }

        if ($request->has('branch_id') && $request->branch_id) {
            $query->whereHas('staff', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $expenses = $query->orderBy('total_amount', 'desc')->get();

        // Calculate summary
        $summary = [
            'total_staff' => $expenses->count(),
            'total_amount' => $expenses->sum('total_amount'),
            'total_days_present' => $expenses->sum('days_present'),
            'total_days_absent' => $expenses->sum('days_absent'),
            'average_per_staff' => $expenses->count() > 0
                ? round($expenses->sum('total_amount') / $expenses->count(), 2)
                : 0,
            'pending_count' => $expenses->where('status', 'pending')->count(),
            'approved_count' => $expenses->where('status', 'approved')->count(),
            'paid_count' => $expenses->where('status', 'paid')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $expenses,
            'summary' => $summary,
            'week' => [
                'start' => $weekStart->format('Y-m-d'),
                'end' => $weekStart->copy()->endOfWeek(Carbon::SATURDAY)->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Update expense status (approve/pay)
     */
    public function updateExpenseStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,approved,paid,cancelled',
            'remarks' => 'nullable|string',
        ]);

        $user = $request->user();
        $expense = StaffWeeklyExpense::findOrFail($id);

        $updateData = [
            'status' => $request->status,
            'remarks' => $request->remarks,
            'updated_by' => $user->id,
        ];

        if ($request->status === 'approved') {
            $updateData['approved_by'] = $user->id;
            $updateData['approved_at'] = now();
        }

        $expense->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Expense status updated successfully',
            'data' => $expense,
        ]);
    }

    /**
     * Bulk update expense status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'expense_ids' => 'required|array',
            'expense_ids.*' => 'exists:staff_weekly_expenses,id',
            'status' => 'required|in:pending,approved,paid,cancelled',
        ]);

        $user = $request->user();

        $updateData = [
            'status' => $request->status,
            'updated_by' => $user->id,
        ];

        if ($request->status === 'approved') {
            $updateData['approved_by'] = $user->id;
            $updateData['approved_at'] = now();
        }

        StaffWeeklyExpense::whereIn('id', $request->expense_ids)->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => count($request->expense_ids) . ' expenses updated successfully',
        ]);
    }

    /**
     * Get weekly expense summary
     */
    public function getWeeklySummary(Request $request): JsonResponse
    {
        $request->validate([
            'week_start_date' => 'required|date',
        ]);

        $weekStart = Carbon::parse($request->week_start_date)->startOfWeek(Carbon::MONDAY);

        $query = WeeklyExpenseSummary::with([
            'expenseCategory:id,name,slug',
            'branch:id,name',
        ])->where('week_start_date', $weekStart->format('Y-m-d'));

        if ($request->has('branch_id') && $request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        $summaries = $query->get();

        // Get grand total
        $grandTotal = [
            'total_staff' => $summaries->sum('total_staff'),
            'total_amount' => $summaries->sum('total_amount'),
            'total_days_paid' => $summaries->sum('total_days_paid'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $summaries,
            'grand_total' => $grandTotal,
        ]);
    }

    /**
     * Get staff with their allowance eligibility
     */
    public function getStaffForAllowance(Request $request): JsonResponse
    {
        $query = Staff::with(['branch:id,name', 'role:id,name'])
            ->select('id', 'first_name', 'last_name', 'code', 'branch_id', 'role_id', 'has_access_in_crm', 'salary_payout', 'status_id')
            ->where('status_id', 1); // Only active staff

        if ($request->has('branch_id') && $request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('category_id') && $request->category_id) {
            // Include existing allowance info for this category
            $query->with(['staffAllowances' => function ($q) use ($request) {
                $q->where('expense_category_id', $request->category_id)
                    ->where('is_active', true);
            }]);
        }

        $staff = $query->orderBy('first_name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $staff,
        ]);
    }

    /**
     * Calculate weekly attendance for a staff member
     */
    private function calculateWeeklyAttendance(int $staffId, Carbon $weekStart, Carbon $weekEnd, bool $checkAttendance): array
    {
        $daysExpected = 6; // Mon-Sat (Sunday off)
        $daysPresent = 0;
        $daysAbsent = 0;
        $daysLeave = 0;
        $workingDays = 0;

        if (!$checkAttendance) {
            // If not checking attendance, assume full presence
            return [
                'days_expected' => $daysExpected,
                'days_present' => $daysExpected,
                'days_absent' => 0,
                'days_leave' => 0,
                'working_days' => $daysExpected,
            ];
        }

        // Get attendance records for the week
        $attendances = Attendance::where('staff_id', $staffId)
            ->whereBetween('date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        // Loop through each day of the week (Mon-Sat)
        $currentDate = $weekStart->copy();
        while ($currentDate <= $weekEnd && $currentDate->dayOfWeek !== Carbon::SUNDAY) {
            $dateKey = $currentDate->format('Y-m-d');

            // Skip Sundays
            if ($currentDate->dayOfWeek === Carbon::SUNDAY) {
                $currentDate->addDay();
                continue;
            }

            $attendance = $attendances->get($dateKey);

            if ($attendance) {
                if ($attendance->status === 'present' || $attendance->status === 'half_day') {
                    $daysPresent++;
                    $workingDays++;
                } elseif ($attendance->status === 'leave') {
                    $daysLeave++;
                } else {
                    $daysAbsent++;
                }
            } else {
                // No attendance record = absent
                $daysAbsent++;
            }

            $currentDate->addDay();
        }

        return [
            'days_expected' => $daysExpected,
            'days_present' => $daysPresent,
            'days_absent' => $daysAbsent,
            'days_leave' => $daysLeave,
            'working_days' => $workingDays,
        ];
    }
}
