<?php

namespace App\Http\Controllers\Authenticated\StaffApp;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * Get today's attendance for authenticated staff
     */
    public function today(Request $request)
    {
        $staff = Auth::user();
        $today = Carbon::today()->toDateString();

        $attendance = Attendance::where('staff_id', $staff->id)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'date' => $today,
                    'hasCheckedIn' => false,
                    'hasCheckedOut' => false,
                    'checkInTime' => null,
                    'checkOutTime' => null,
                    'workingHours' => 0,
                    'breakTime' => 0,
                    'currentStatus' => 'not_checked_in',
                    'checkInStatus' => null,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'date' => $attendance->date,
                'hasCheckedIn' => !is_null($attendance->check_in_time),
                'hasCheckedOut' => !is_null($attendance->check_out_time),
                'checkInTime' => $attendance->check_in_time?->toISOString(),
                'checkOutTime' => $attendance->check_out_time?->toISOString(),
                'workingHours' => $attendance->working_hours ?? 0,
                'breakTime' => $attendance->break_time ?? 0,
                'currentStatus' => $attendance->status ?? 'present',
                'checkInStatus' => $this->getCheckInStatus($attendance->check_in_time),
                'checkInPhoto' => $attendance->check_in_photo,
                'checkOutPhoto' => $attendance->check_out_photo,
            ],
        ]);
    }


    /**
     * Check in attendance
     */
    public function checkIn(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'photo' => 'required|string', // Photo is required for verification
            'notes' => 'nullable|string',
        ]);

        $staff = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        // Check if already checked in today
        $existing = Attendance::where('staff_id', $staff->id)
            ->where('date', $today)
            ->first();

        if ($existing && $existing->check_in_time) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already checked in today',
            ], 400);
        }

        // Extract photo path from notes if provided
        $photoPath = null;
        if ($request->notes && str_contains($request->notes, 'Photo:')) {
            $photoPath = trim(str_replace('Photo:', '', $request->notes));
        } elseif ($request->photo) {
            $photoPath = $request->photo;
        }

        $attendance = Attendance::updateOrCreate(
            [
                'staff_id' => $staff->id,
                'date' => $today,
            ],
            [
                'check_in_time' => $now,
                'check_in_latitude' => $request->latitude,
                'check_in_longitude' => $request->longitude,
                'check_in_location' => $this->getLocationString($request->latitude, $request->longitude),
                'check_in_photo' => $photoPath,
                'status' => 'present',
                'notes' => $request->notes,
            ]
        );
Log::info($attendance);
        return response()->json([
            'status' => 'success',
            'message' => 'Checked in successfully',
            'data' => [
                'checkInTime' => $attendance->check_in_time->toISOString(),
                'checkInStatus' => $this->getCheckInStatus($attendance->check_in_time),
            ],
        ]);
    }

    /**
     * Check out attendance
     */
    public function checkOut(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'photo' => 'required|string', // Photo is required for verification
            'notes' => 'nullable|string',
        ]);

        $staff = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        $attendance = Attendance::where('staff_id', $staff->id)
            ->where('date', $today)
            ->first();

        if (!$attendance || !$attendance->check_in_time) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must check in before checking out',
            ], 400);
        }

        if ($attendance->check_out_time) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already checked out today',
            ], 400);
        }

        // Extract photo path from notes if provided
        $photoPath = null;
        if ($request->notes && str_contains($request->notes, 'Photo:')) {
            $photoPath = trim(str_replace('Photo:', '', $request->notes));
        } elseif ($request->photo) {
            $photoPath = $request->photo;
        }

        $attendance->update([
            'check_out_time' => $now,
            'check_out_latitude' => $request->latitude,
            'check_out_longitude' => $request->longitude,
            'check_out_location' => $this->getLocationString($request->latitude, $request->longitude),
            'check_out_photo' => $photoPath,
        ]);

        $attendance->calculateWorkingHours();
        $attendance->determineStatus();

        return response()->json([
            'status' => 'success',
            'message' => 'Checked out successfully',
            'data' => [
                'checkOutTime' => $attendance->check_out_time->toISOString(),
                'workingHours' => $attendance->working_hours,
            ],
        ]);
    }

    /**
     * Get attendance history
     */
    public function history(Request $request)
    {
        $staff = Auth::user();
        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10);
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');
        $status = $request->input('status'); // Filter by status

        $query = Attendance::where('staff_id', $staff->id)
            ->orderBy('date', 'desc');

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $attendances = $query->paginate($perPage, ['*'], 'page', $page);

        $data = $attendances->map(function ($attendance) {
            return [
                'id' => $attendance->id,
                'date' => $attendance->date,
                'dayName' => Carbon::parse($attendance->date)->format('l'),
                'checkInTime' => $attendance->check_in_time?->format('H:i'),
                'checkOutTime' => $attendance->check_out_time?->format('H:i'),
                'workingHours' => $attendance->working_hours ?? 0,
                'status' => $attendance->status,
                'isToday' => $attendance->date === Carbon::today()->toDateString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
            ],
        ]);
    }

    /**
     * Get single attendance record details
     */
    public function show($id)
    {
        $staff = Auth::user();
        
        $attendance = Attendance::where('staff_id', $staff->id)
            ->where('id', $id)
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance record not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $attendance->id,
                'date' => $attendance->date,
                'dayName' => Carbon::parse($attendance->date)->format('l'),
                'checkInTime' => $attendance->check_in_time?->toISOString(),
                'checkOutTime' => $attendance->check_out_time?->toISOString(),
                'checkInTimeFormatted' => $attendance->check_in_time?->format('h:i A'),
                'checkOutTimeFormatted' => $attendance->check_out_time?->format('h:i A'),
                'workingHours' => $attendance->working_hours ?? 0,
                'breakTime' => $attendance->break_time ?? 0,
                'status' => $attendance->status,
                'checkInStatus' => $this->getCheckInStatus($attendance->check_in_time),
                'checkInPhoto' => $attendance->check_in_photo,
                'checkOutPhoto' => $attendance->check_out_photo,
                'checkInLatitude' => $attendance->check_in_latitude,
                'checkInLongitude' => $attendance->check_in_longitude,
                'checkInLocation' => $attendance->check_in_location,
                'checkOutLatitude' => $attendance->check_out_latitude,
                'checkOutLongitude' => $attendance->check_out_longitude,
                'checkOutLocation' => $attendance->check_out_location,
                'notes' => $attendance->notes,
            ],
        ]);
    }

    /**
     * Get monthly attendance statistics
     */
    public function stats(Request $request)
    {
        $staff = Auth::user();
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        $totalDays = $endDate->day;

        $attendances = Attendance::where('staff_id', $staff->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $presentDays = $attendances->where('status', 'present')->count();
        $absentDays = $totalDays - $attendances->count();
        $lateDays = $attendances->filter(function ($att) {
            return $this->getCheckInStatus($att->check_in_time) === 'late';
        })->count();
        $halfDays = $attendances->where('status', 'half_day')->count();

        $totalWorkingHours = $attendances->sum('working_hours');
        $averageWorkingHours = $presentDays > 0 ? $totalWorkingHours / $presentDays : 0;

        // Calculate streak
        $currentStreak = $this->calculateStreak($staff->id, $endDate);

        $attendancePercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'month' => $month,
                'year' => $year,
                'totalDays' => $totalDays,
                'presentDays' => $presentDays,
                'absentDays' => $absentDays,
                'lateDays' => $lateDays,
                'halfDays' => $halfDays,
                'attendancePercentage' => $attendancePercentage,
                'totalWorkingHours' => round($totalWorkingHours, 1),
                'averageWorkingHours' => round($averageWorkingHours, 1),
                'currentStreak' => $currentStreak,
                'totalOvertime' => 0, // Can be calculated based on expected hours
            ],
        ]);
    }

    /**
     * Helper: Get check-in status (on time or late)
     */
    private function getCheckInStatus($checkInTime)
    {
        if (!$checkInTime) {
            return null;
        }

        $checkInHour = Carbon::parse($checkInTime)->hour;
        $checkInMinute = Carbon::parse($checkInTime)->minute;

        // Assuming work starts at 9:00 AM
        if ($checkInHour < 9 || ($checkInHour === 9 && $checkInMinute === 0)) {
            return 'on_time';
        }

        return 'late';
    }

    /**
     * Helper: Get location string from coordinates
     */
    private function getLocationString($latitude, $longitude)
    {
        return "{$latitude}, {$longitude}";
    }

    /**
     * Helper: Calculate current attendance streak
     */
    private function calculateStreak($staffId, $endDate)
    {
        $streak = 0;
        $currentDate = $endDate->copy();

        while (true) {
            $attendance = Attendance::where('staff_id', $staffId)
                ->where('date', $currentDate->toDateString())
                ->where('status', 'present')
                ->first();

            if (!$attendance) {
                break;
            }

            $streak++;
            $currentDate->subDay();

            // Limit to prevent infinite loop
            if ($streak > 365) {
                break;
            }
        }

        return $streak;
    }

    /**
     * Export attendance report
     */
    public function exportReport(Request $request)
    {
        $staff = Auth::user();
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);
        $format = $request->input('format', 'csv'); // csv or excel

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $attendances = Attendance::where('staff_id', $staff->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date', 'asc')
            ->get();

        // Prepare data for export
        $data = [];
        $data[] = ['Date', 'Day', 'Check In', 'Check Out', 'Working Hours', 'Status'];

        foreach ($attendances as $attendance) {
            $data[] = [
                $attendance->date,
                Carbon::parse($attendance->date)->format('l'),
                $attendance->check_in_time?->format('H:i') ?? '-',
                $attendance->check_out_time?->format('H:i') ?? '-',
                $attendance->working_hours ?? 0,
                ucfirst($attendance->status),
            ];
        }

        // Summary row
        $totalPresent = $attendances->where('status', 'present')->count();
        $totalAbsent = $endDate->day - $attendances->count();
        $totalHours = $attendances->sum('working_hours');

        $data[] = [];
        $data[] = ['Summary', '', '', '', '', ''];
        $data[] = ['Total Present Days', $totalPresent, '', '', '', ''];
        $data[] = ['Total Absent Days', $totalAbsent, '', '', '', ''];
        $data[] = ['Total Working Hours', $totalHours, '', '', '', ''];

        // Generate CSV
        $filename = "attendance_{$staff->name}_{$month}_{$year}.csv";
        $handle = fopen('php://temp', 'r+');
        
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
