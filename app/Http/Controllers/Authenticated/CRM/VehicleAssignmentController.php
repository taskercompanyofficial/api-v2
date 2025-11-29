<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\VehicleAssignment;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleAssignmentController extends Controller
{
    use QueryFilterTrait;

    protected $allowedFilters = [
        'vehicle_id',
        'staff_id',
        'assignment_type',
        'status',
        'assignment_date',
        'expected_return_date'
    ];

    protected $allowedSorts = [
        'id',
        'vehicle_id',
        'staff_id',
        'assignment_date',
        'expected_return_date',
        'actual_return_date',
        'status',
        'created_at'
    ];

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;
        
        $query = VehicleAssignment::query()->with(['vehicle:id,vehicle_number,make,model', 'staff:id,full_name']);
        
        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);
        
        $this->applyUrlFilters($query, $request, [
            'vehicle_id', 'staff_id', 'assignment_type', 'status', 'assignment_date', 'expected_return_date'
        ]);
        
        $assignments = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $assignments]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'staff_id' => 'required|exists:staff,id',
            'assignment_date' => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:assignment_date',
            'actual_return_date' => 'nullable|date|after_or_equal:assignment_date',
            'start_mileage' => 'nullable|integer|min:0',
            'end_mileage' => 'nullable|integer|min:0|gte:start_mileage',
            'assignment_type' => 'string|max:50',
            'purpose' => 'nullable|string|max:200',
            'assignment_notes' => 'nullable|string',
            'return_notes' => 'nullable|string',
            'assigned_by' => 'nullable|string|max:100',
            'status' => 'in:active,returned,overdue,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if vehicle is available
        $vehicle = \App\Models\Vehicle::find($request->vehicle_id);
        if ($vehicle->status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle is not available for assignment'
            ], 422);
        }

        $assignment = VehicleAssignment::create($request->all());

        // Update vehicle status
        if ($request->status === 'active' || !$request->has('status')) {
            $vehicle->update(['status' => 'assigned']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicle assignment created successfully',
            'data' => $assignment->load(['vehicle:id,vehicle_number,make,model', 'staff:id,full_name'])
        ], 201);
    }

    public function show(VehicleAssignment $vehicleAssignment)
    {
        return response()->json([
            'success' => true,
            'data' => $vehicleAssignment->load(['vehicle:id,vehicle_number,make,model', 'staff:id,full_name'])
        ]);
    }

    public function update(Request $request, VehicleAssignment $vehicleAssignment)
    {
        $validator = Validator::make($request->all(), [
            'assignment_date' => 'sometimes|date',
            'expected_return_date' => 'nullable|date|after_or_equal:assignment_date',
            'actual_return_date' => 'nullable|date|after_or_equal:assignment_date',
            'start_mileage' => 'nullable|integer|min:0',
            'end_mileage' => 'nullable|integer|min:0|gte:start_mileage',
            'assignment_type' => 'sometimes|string|max:50',
            'purpose' => 'nullable|string|max:200',
            'assignment_notes' => 'nullable|string',
            'return_notes' => 'nullable|string',
            'assigned_by' => 'nullable|string|max:100',
            'status' => 'sometimes|in:active,returned,overdue,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $vehicleAssignment->status;
        $vehicleAssignment->update($request->all());

        // Update vehicle status if assignment status changed to returned
        if ($oldStatus !== 'returned' && $request->status === 'returned') {
            $vehicleAssignment->vehicle->update(['status' => 'available']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicle assignment updated successfully',
            'data' => $vehicleAssignment->load(['vehicle:id,vehicle_number,make,model', 'staff:id,full_name'])
        ]);
    }

    public function destroy(VehicleAssignment $vehicleAssignment)
    {
        $vehicleAssignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle assignment deleted successfully'
        ]);
    }
}