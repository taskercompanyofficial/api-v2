<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Staff;
use App\Models\SalaryPayout;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            }, 'salaryPayouts' => function ($q) use ($month) {
                $q->where('month', $month);
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
                    if (isset($filter['id'])) {
                        if ($filter['id'] === 'name') $filter['id'] = 'first_name';
                        if ($filter['id'] === 'monthly_salary') $filter['id'] = 'salary_payout';
                        if ($filter['id'] === 'branch') $filter['id'] = 'branch_id';
                        if ($filter['id'] === 'code') $filter['id'] = 'code';
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
                    if ($rule['id'] === 'monthly_salary') $rule['id'] = 'salary_payout';
                    if ($rule['id'] === 'name') $rule['id'] = 'first_name';
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

            // Get approved leave applications for the month
            $approvedLeaves = $staffMember->leaveApplications()
                ->where('status', 'approved')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })
                ->get();

            // Create a map of year-month-day for efficient lookup
            $attendanceMap = $attendances->keyBy(function ($item) {
                return \Carbon\Carbon::parse($item->date)->format('Y-m-d');
            });

            // Count different attendance statuses and calculate effective days
            $presentDays = 0;
            $halfDays = 0;
            $paidHolidays = 0;
            $approvedLeaveDays = 0;
            $totalWorkingHours = 0;

            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $isHoliday = $currentDate->isSunday();
                $isOnLeave = $approvedLeaves->contains(function ($leave) use ($currentDate) {
                    return $currentDate->between($leave->start_date, $leave->end_date);
                });

                if ($isHoliday) {
                    // Check if Saturday (previous day) or Monday (next day) was absent
                    $saturday = $currentDate->copy()->subDay();
                    $monday = $currentDate->copy()->addDay();

                    $saturdayStr = $saturday->format('Y-m-d');
                    $mondayStr = $monday->format('Y-m-d');

                    // Check if Saturday or Monday exists in the attendance map with present/late/half_day status
                    $saturdayPresent = isset($attendanceMap[$saturdayStr]) &&
                        in_array($attendanceMap[$saturdayStr]->status, ['present', 'late', 'half_day']);
                    $mondayPresent = isset($attendanceMap[$mondayStr]) &&
                        in_array($attendanceMap[$mondayStr]->status, ['present', 'late', 'half_day']);

                    // Check if Saturday or Monday was on approved leave
                    $saturdayOnLeave = $approvedLeaves->contains(function ($leave) use ($saturday) {
                        return $saturday->between($leave->start_date, $leave->end_date);
                    });
                    $mondayOnLeave = $approvedLeaves->contains(function ($leave) use ($monday) {
                        return $monday->between($leave->start_date, $leave->end_date);
                    });

                    // Only count Sunday as paid holiday if both Saturday AND Monday were present or on leave
                    // If either was absent, deduct the Sunday pay
                    if (($saturdayPresent || $saturdayOnLeave) && ($mondayPresent || $mondayOnLeave)) {
                        $paidHolidays++;
                    }
                    // Note: We still count working hours if they worked on Sunday
                    if (isset($attendanceMap[$dateStr])) {
                        $totalWorkingHours += (float) $attendanceMap[$dateStr]->working_hours;
                    }
                } elseif ($isOnLeave) {
                    $approvedLeaveDays++;
                } elseif (isset($attendanceMap[$dateStr])) {
                    $att = $attendanceMap[$dateStr];
                    $totalWorkingHours += (float) $att->working_hours;

                    if ($att->status === 'present' || $att->status === 'late') {
                        $presentDays++;
                    } elseif ($att->status === 'half_day') {
                        $halfDays++;
                    }
                }

                $currentDate->addDay();
            }

            // Calculate effective working days
            // (Half days count as 0.5)
            $effectiveWorkingDays = $presentDays + ($halfDays * 0.5) + $paidHolidays + $approvedLeaveDays;

            // Calculate daily rate
            $monthlySalary = (float) $staffMember->salary_payout;
            $dailyRate = $monthlySalary / $totalDaysInMonth;

            // Calculate payable salary
            $payableSalary = $dailyRate * $effectiveWorkingDays;

            // Absent days are those that are not present, not half, not Sunday, and not on leave
            $absentDays = $totalDaysInMonth - ($presentDays + $halfDays + $paidHolidays + $approvedLeaveDays);

            $payout = $staffMember->salaryPayouts->first();

            return [
                'id' => $staffMember->id,
                'code' => $staffMember->code,
                'name' => trim($staffMember->first_name . ' ' . ($staffMember->middle_name ?? '') . ' ' . $staffMember->last_name),
                'branch' => $staffMember->branch ? $staffMember->branch->name : null,
                'branch_id' => $staffMember->branch_id,
                'monthly_salary' => $payout ? number_format($payout->base_salary, 2) : number_format($monthlySalary, 2),
                'daily_rate' => $payout ? number_format($payout->daily_rate, 2) : number_format($dailyRate, 2),
                'total_days' => $payout ? $payout->total_days : $totalDaysInMonth,
                'present_days' => $presentDays,
                'half_days' => $halfDays,
                'absent_days' => max(0, $absentDays),
                'paid_holidays' => $paidHolidays,
                'approved_leaves' => $approvedLeaveDays,
                'effective_working_days' => $payout ? (float) $payout->effective_days : round($effectiveWorkingDays, 2),
                'total_working_hours' => round($totalWorkingHours, 2),
                'payable_salary' => $payout ? number_format($payout->final_payable, 2) : number_format($payableSalary, 2),
                'deduction' => $payout ? number_format($payout->manual_deduction + $payout->calculated_deduction, 2) : number_format($monthlySalary - $payableSalary, 2),
                'attendance_percentage' => $totalDaysInMonth > 0 ? round(($effectiveWorkingDays / $totalDaysInMonth) * 100, 1) : 0,
                'payout' => $payout,
                'status' => $payout ? $payout->status : 'draft',
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

        $staff = Staff::with(['branch:id,name', 'staffRole:id,name', 'salaryPayouts' => function ($q) use ($month) {
            $q->where('month', $month);
        }])
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

        // Get approved leave applications for the month
        $approvedLeaves = $staff->leaveApplications()
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->get();

        $attendanceMap = $attendances->keyBy(function ($item) {
            return \Carbon\Carbon::parse($item->date)->format('Y-m-d');
        });

        // Calculate metrics including holidays and leaves
        $presentDays = 0;
        $halfDays = 0;
        $lateDays = 0;
        $paidHolidays = 0;
        $approvedLeaveDays = 0;
        $totalWorkingHours = 0;
        $dailyBreakdown = [];

        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $isSunday = $currentDate->isSunday();
            $isOnLeave = $approvedLeaves->contains(function ($leave) use ($currentDate) {
                return $currentDate->between($leave->start_date, $leave->end_date);
            });

            $attendance = $attendanceMap->get($dateStr);
            $status = 'absent';
            $checkIn = null;
            $checkOut = null;
            $hours = 0;

            if ($isSunday) {
                // Check if Saturday (previous day) or Monday (next day) was absent
                $saturday = $currentDate->copy()->subDay();
                $monday = $currentDate->copy()->addDay();

                $saturdayStr = $saturday->format('Y-m-d');
                $mondayStr = $monday->format('Y-m-d');

                // Check if Saturday or Monday exists in the attendance map with present/late/half_day status
                $saturdayPresent = isset($attendanceMap[$saturdayStr]) &&
                    in_array($attendanceMap[$saturdayStr]->status ?? 'absent', ['present', 'late', 'half_day']);
                $mondayPresent = isset($attendanceMap[$mondayStr]) &&
                    in_array($attendanceMap[$mondayStr]->status ?? 'absent', ['present', 'late', 'half_day']);

                // Check if Saturday or Monday was on approved leave
                $saturdayOnLeave = $approvedLeaves->contains(function ($leave) use ($saturday) {
                    return $saturday->between($leave->start_date, $leave->end_date);
                });
                $mondayOnLeave = $approvedLeaves->contains(function ($leave) use ($monday) {
                    return $monday->between($leave->start_date, $leave->end_date);
                });

                // Only count Sunday as paid holiday if both Saturday AND Monday were present or on leave
                if (($saturdayPresent || $saturdayOnLeave) && ($mondayPresent || $mondayOnLeave)) {
                    $status = 'holiday';
                    $paidHolidays++;
                } else {
                    // Sunday pay is deducted because of adjacent absence
                    $status = 'holiday_deducted';
                }

                if ($attendance) {
                    $totalWorkingHours += (float) $attendance->working_hours;
                    $checkIn = $attendance->check_in_time;
                    $checkOut = $attendance->check_out_time;
                    $hours = $attendance->working_hours;
                }
            } elseif ($isOnLeave) {
                $status = 'leave';
                $approvedLeaveDays++;
            } elseif ($attendance) {
                $status = $attendance->status;
                $checkIn = $attendance->check_in_time;
                $checkOut = $attendance->check_out_time;
                $hours = $attendance->working_hours;
                $totalWorkingHours += (float) $hours;

                if ($status === 'present') {
                    $presentDays++;
                } elseif ($status === 'late') {
                    $presentDays++;
                    $lateDays++;
                } elseif ($status === 'half_day') {
                    $halfDays++;
                }
            }

            $dailyBreakdown[] = [
                'date' => $dateStr,
                'day' => $currentDate->format('l'),
                'status' => $status,
                'check_in_time' => $checkIn,
                'check_out_time' => $checkOut,
                'working_hours' => $hours,
            ];

            $currentDate->addDay();
        }

        // Calculate effective working days
        $effectiveWorkingDays = $presentDays + ($halfDays * 0.5) + $paidHolidays + $approvedLeaveDays;

        // Calculate salary
        $monthlySalary = (float) $staff->salary_payout;
        $dailyRate = $monthlySalary / $totalDaysInMonth;
        $payableSalary = $dailyRate * $effectiveWorkingDays;
        $deduction = $monthlySalary - $payableSalary;

        $avgWorkingHours = ($presentDays + $halfDays > 0) ? $totalWorkingHours / ($presentDays + $halfDays) : 0;

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
                    'profile_image' => $staff->profile_image,
                    'payout' => $staff->salaryPayouts->first(),
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
                    'absent_days' => max(0, $totalDaysInMonth - ($presentDays + $halfDays + $paidHolidays + $approvedLeaveDays)),
                    'paid_holidays' => $paidHolidays,
                    'approved_leaves' => $approvedLeaveDays,
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

    /**
     * Post salary for a staff member (Mark as pending payment)
     */
    public function postSalary(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'month' => 'required|string',
            'base_salary' => 'required|numeric',
            'daily_rate' => 'required|numeric',
            'total_days' => 'required|integer',
            'effective_days' => 'required|numeric',
            'relief_absents' => 'nullable|integer',
            'manual_deduction' => 'nullable|numeric',
            'advance_adjustment' => 'nullable|numeric',
            'calculated_deduction' => 'required|numeric',
            'final_payable' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();

        $payout = SalaryPayout::updateOrCreate(
            ['staff_id' => $validated['staff_id'], 'month' => $validated['month']],
            [
                ...$validated,
                'status' => 'posted',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Salary posted successfully',
            'data' => $payout,
        ]);
    }

    /**
     * Mark salary as paid
     */
    public function markAsPaid(Request $request, $id)
    {
        $payout = SalaryPayout::findOrFail($id);

        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'paid_amount' => 'required|numeric',
            'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
        ]);

        $user = $request->user();

        if ($request->hasFile('payment_proof')) {
            $path = $request->file('payment_proof')->store('salary-payouts', 'public');
            $payout->payment_proof = $path;
        }

        $payout->update([
            'transaction_id' => $validated['transaction_id'],
            'paid_amount' => $validated['paid_amount'],
            'status' => 'paid',
            'paid_at' => now(),
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Salary payment recorded successfully',
            'data' => $payout,
        ]);
    }
}
