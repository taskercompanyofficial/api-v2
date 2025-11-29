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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
