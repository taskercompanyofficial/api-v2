<?php

namespace App\Http\Controllers\Authenticated\StaffApp;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    /**
     * Get all active leave types
     */
    public function types(Request $request)
    {
        $leaveTypes = LeaveType::active()->get();

        return response()->json([
            'status' => 'success',
            'data' => $leaveTypes->map(fn($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'description' => $type->description,
                'daysPerYear' => $type->days_per_year,
                'requiresApproval' => $type->requires_approval,
                'isPaid' => $type->is_paid,
                'color' => $type->color,
            ]),
        ]);
    }

    /**
     * Get leave balance for current year
     */
    public function balance(Request $request)
    {
        $staff = Auth::user();
        $year = $request->input('year', date('Y'));

        $balances = LeaveBalance::where('staff_id', $staff->id)
            ->where('year', $year)
            ->with('leaveType')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $balances->map(fn($balance) => [
                'id' => $balance->id,
                'leaveType' => [
                    'id' => $balance->leaveType->id,
                    'name' => $balance->leaveType->name,
                    'code' => $balance->leaveType->code,
                    'color' => $balance->leaveType->color,
                ],
                'year' => $balance->year,
                'totalDays' => $balance->total_days,
                'usedDays' => $balance->used_days,
                'pendingDays' => $balance->pending_days,
                'availableDays' => $balance->available_days,
            ]),
        ]);
    }

    /**
     * Get leave applications for authenticated staff
     */
    public function index(Request $request)
    {
        $staff = Auth::user();
        
        $query = LeaveApplication::where('staff_id', $staff->id)
            ->with(['leaveType', 'approver']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        $applications = $query->orderBy('applied_at', 'desc')
            ->paginate($request->input('per_page', 20));

        $items = collect($applications->items())->map(fn($app) => $this->formatApplication($app));

        return response()->json([
            'status' => 'success',
            'data' => [
                'applications' => $items,
                'pagination' => [
                    'currentPage' => $applications->currentPage(),
                    'lastPage' => $applications->lastPage(),
                    'perPage' => $applications->perPage(),
                    'total' => $applications->total(),
                ],
            ],
        ]);
    }

    /**
     * Get single leave application details
     */
    public function show($id)
    {
        $staff = Auth::user();
        
        $application = LeaveApplication::where('id', $id)
            ->where('staff_id', $staff->id)
            ->with(['leaveType', 'approver'])
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $this->formatApplication($application),
        ]);
    }

    /**
     * Apply for leave
     */
    public function apply(Request $request)
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'attachments' => 'nullable|array',
        ]);

        $staff = Auth::user();
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        // Calculate working days
        $totalDays = LeaveApplication::calculateWorkingDays($startDate, $endDate);

        if ($totalDays <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave period must include at least one working day',
            ], 400);
        }

        // Check leave balance
        $balance = LeaveBalance::where('staff_id', $staff->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $startDate->year)
            ->first();

        if (!$balance) {
            // Create balance if doesn't exist
            $leaveType = LeaveType::findOrFail($request->leave_type_id);
            $balance = LeaveBalance::create([
                'staff_id' => $staff->id,
                'leave_type_id' => $request->leave_type_id,
                'year' => $startDate->year,
                'total_days' => $leaveType->days_per_year,
                'used_days' => 0,
                'pending_days' => 0,
                'available_days' => $leaveType->days_per_year,
            ]);
        }

        if ($balance->available_days < $totalDays) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient leave balance. Available: ' . $balance->available_days . ' days',
            ], 400);
        }

        // Check for overlapping leaves
        $overlapping = LeaveApplication::where('staff_id', $staff->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                          ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($overlapping) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have an overlapping leave application for this period',
            ], 400);
        }

        // Create leave application
        $application = LeaveApplication::create([
            'staff_id' => $staff->id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'reason' => $request->reason,
            'status' => 'pending',
            'applied_at' => now(),
            'attachments' => $request->attachments,
        ]);

        // Update pending balance
        $balance->addPendingDays($totalDays);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave application submitted successfully',
            'data' => $this->formatApplication($application->load(['leaveType', 'approver'])),
        ], 201);
    }

    /**
     * Cancel leave application
     */
    public function cancel($id)
    {
        $staff = Auth::user();
        
        $application = LeaveApplication::where('id', $id)
            ->where('staff_id', $staff->id)
            ->whereIn('status', ['pending', 'approved'])
            ->firstOrFail();

        // Don't allow cancellation if leave has already started
        if (Carbon::parse($application->start_date)->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot cancel leave that has already started',
            ], 400);
        }

        $application->cancel();

        return response()->json([
            'status' => 'success',
            'message' => 'Leave application cancelled successfully',
            'data' => $this->formatApplication($application->load(['leaveType', 'approver'])),
        ]);
    }

    /**
     * Approve leave application (for managers/admins)
     */
    public function approve($id, Request $request)
    {
        $approver = Auth::user();
        
        $application = LeaveApplication::where('id', $id)
            ->where('status', 'pending')
            ->with(['leaveType', 'staff'])
            ->firstOrFail();

        $application->approve($approver->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave application approved successfully',
            'data' => $this->formatApplication($application->load(['leaveType', 'approver'])),
        ]);
    }

    /**
     * Reject leave application (for managers/admins)
     */
    public function reject($id, Request $request)
    {
        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $approver = Auth::user();
        
        $application = LeaveApplication::where('id', $id)
            ->where('status', 'pending')
            ->with(['leaveType', 'staff'])
            ->firstOrFail();

        $application->reject($approver->id, $request->reason);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave application rejected',
            'data' => $this->formatApplication($application->load(['leaveType', 'approver'])),
        ]);
    }

    /**
     * Format leave application for response
     */
    private function formatApplication(LeaveApplication $application): array
    {
        return [
            'id' => $application->id,
            'leaveType' => [
                'id' => $application->leaveType->id,
                'name' => $application->leaveType->name,
                'code' => $application->leaveType->code,
                'color' => $application->leaveType->color,
            ],
            'startDate' => $application->start_date->format('Y-m-d'),
            'endDate' => $application->end_date->format('Y-m-d'),
            'totalDays' => $application->total_days,
            'reason' => $application->reason,
            'status' => $application->status,
            'appliedAt' => $application->applied_at->toISOString(),
            'approvedBy' => $application->approver ? $application->approver->name : null,
            'approvedAt' => $application->approved_at?->toISOString(),
            'rejectionReason' => $application->rejection_reason,
            'attachments' => $application->attachments,
        ];
    }
}
