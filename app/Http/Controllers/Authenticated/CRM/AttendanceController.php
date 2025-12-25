<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Staff;
use App\QueryFilterTrait;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use QueryFilterTrait;
    /**
     * Get attendance statistics for a specific date.
     */
    public function statistics(Request $request)
    {
        $targetDate = $request->input('date', now()->toDateString());

        // Get all staff count
        $totalStaff = Staff::count();

        // Get attendance records for the target date
        $attendances = Attendance::where('date', $targetDate)->get();

        // Calculate statistics
        $totalPresent = $attendances->whereIn('status', ['present', 'late'])->count();
        $totalAbsent = $totalStaff - $totalPresent;
        $totalLate = $attendances->where('status', 'late')->count();
        $avgWorkingHours = $attendances->where('working_hours', '>', 0)->avg('working_hours') ?? 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_staff' => $totalStaff,
                'total_present' => $totalPresent,
                'total_absent' => $totalAbsent,
                'total_late' => $totalLate,
                'average_working_hours' => round($avgWorkingHours, 2),
                'present_percentage' => $totalStaff > 0 ? round(($totalPresent / $totalStaff) * 100, 1) : 0,
                'absent_percentage' => $totalStaff > 0 ? round(($totalAbsent / $totalStaff) * 100, 1) : 0,
                'late_percentage' => $totalStaff > 0 ? round(($totalLate / $totalStaff) * 100, 1) : 0,
            ],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        // Determine the single date we are interested in (default to today)
        $targetDate = $request->input('date', now()->toDateString());

        // Get all staff with their attendance for the requested single date
        $query = Staff::query()
            ->select(['id', 'first_name', 'last_name'])
            ->with([
                'attendances' => function ($q) use ($targetDate) {
                    $q->where('date', $targetDate);
                }
            ]);

        // Optional filter on attendance status
        if ($request->has('status')) {
            $requestedStatus = $request->input('status');
            if ($requestedStatus === 'absent') {
                // Only staff who do NOT have an attendance record for the target date
                $query->whereDoesntHave('attendances', function ($q) use ($targetDate) {
                    $q->where('date', $targetDate);
                });
            } else {
                // Staff who have an attendance record with the specified status
                $query->whereHas('attendances', function ($q) use ($targetDate, $requestedStatus) {
                    $q->where('date', $targetDate)
                        ->where('status', $requestedStatus);
                });
            }
        }

        $staff = $query->paginate($perPage, ['*'], 'page', $page);

        // Build the correct data structure for the paginated response
        $data = $staff->map(function ($staffMember) {
            return [
                'id' => $staffMember->id,
                'first_name' => $staffMember->first_name,
                'last_name' => $staffMember->last_name,
                'check_in_time' => $staffMember->attendances->first()->check_in_time ?? null,
                'check_in_location' => $staffMember->attendances->first()->check_in_location ?? null,
                'check_out_time' => $staffMember->attendances->first()->check_out_time ?? null,
                'check_out_location' => $staffMember->attendances->first()->check_out_location ?? null,
                'check_in_photo' => $staffMember->attendances->first()->check_in_photo ?? null,
                'check_out_photo' => $staffMember->attendances->first()->check_out_photo ?? null,
                'late_reason' => $staffMember->attendances->first()->late_reason ?? null,
                'early_leave_reason' => $staffMember->attendances->first()->early_leave_reason ?? null,
                'is_manual_checkin' => $staffMember->attendances->first()->is_manual_checkin ?? false,
                'is_manual_checkout' => $staffMember->attendances->first()->is_manual_checkout ?? false,
                'working_hours' => $staffMember->attendances->first()->working_hours ?? null,
                'status' => $staffMember->attendances->first()->status ?? 'absent',
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
        ]);
    }
    /**
     * Store a newly created resource in storage (Check-in).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'date' => 'required|date',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_in_location' => 'nullable|string',
            'check_in_latitude' => 'nullable|numeric',
            'check_in_longitude' => 'nullable|numeric',
            'check_in_photo' => 'nullable|string',
            'late_reason' => 'nullable|string',
            'is_manual_checkin' => 'nullable|boolean',
        ]);

        // Check if attendance already exists for this staff and date
        $existing = Attendance::where('staff_id', $validated['staff_id'])
            ->where('date', $validated['date'])
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance already exists for this staff member on this date',
            ], 422);
        }

        // Set check-in time to now if not provided
        if (!isset($validated['check_in_time'])) {
            $validated['check_in_time'] = now()->format('H:i');
            $validated['is_manual_checkin'] = false;
        } else {
            $validated['is_manual_checkin'] = true;
        }

        // Determine if late (assuming 9:00 AM is standard time)
        $checkInTime = \Carbon\Carbon::parse($validated['check_in_time']);
        $standardTime = \Carbon\Carbon::parse('09:00');
        $status = $checkInTime->greaterThan($standardTime) ? 'late' : 'present';

        $attendance = Attendance::create([
            'staff_id' => $validated['staff_id'],
            'date' => $validated['date'],
            'check_in_time' => $validated['check_in_time'],
            'check_in_location' => $validated['check_in_location'] ?? null,
            'check_in_latitude' => $validated['check_in_latitude'] ?? null,
            'check_in_longitude' => $validated['check_in_longitude'] ?? null,
            'check_in_photo' => $validated['check_in_photo'] ?? null,
            'late_reason' => $validated['late_reason'] ?? null,
            'is_manual_checkin' => $validated['is_manual_checkin'] ?? false,
            'status' => $status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Check-in recorded successfully',
            'data' => $attendance,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage (Check-out).
     */
    public function update(Request $request, string $id)
    {
        $attendance = Attendance::findOrFail($id);

        $validated = $request->validate([
            'check_out_time' => 'nullable|date_format:H:i',
            'check_out_location' => 'nullable|string',
            'check_out_latitude' => 'nullable|numeric',
            'check_out_longitude' => 'nullable|numeric',
            'check_out_photo' => 'nullable|string',
            'early_leave_reason' => 'nullable|string',
            'is_manual_checkout' => 'nullable|boolean',
        ]);

        // Set check-out time to now if not provided
        if (!isset($validated['check_out_time'])) {
            $validated['check_out_time'] = now()->format('H:i');
            $validated['is_manual_checkout'] = false;
        } else {
            $validated['is_manual_checkout'] = true;
        }

        $attendance->update([
            'check_out_time' => $validated['check_out_time'],
            'check_out_location' => $validated['check_out_location'] ?? null,
            'check_out_latitude' => $validated['check_out_latitude'] ?? null,
            'check_out_longitude' => $validated['check_out_longitude'] ?? null,
            'check_out_photo' => $validated['check_out_photo'] ?? null,
            'early_leave_reason' => $validated['early_leave_reason'] ?? null,
            'is_manual_checkout' => $validated['is_manual_checkout'] ?? false,
        ]);

        // Calculate working hours
        $attendance->calculateWorkingHours();

        return response()->json([
            'status' => 'success',
            'message' => 'Check-out recorded successfully',
            'data' => $attendance->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
