<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\VehicleUsageLog;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleUsageLogController extends Controller
{
    use QueryFilterTrait;

    protected $allowedFilters = [
        'vehicle_id',
        'staff_id',
        'usage_date',
        'usage_type',
        'is_approved',
        'start_location',
        'end_location'
    ];

    protected $allowedSorts = [
        'id',
        'vehicle_id',
        'staff_id',
        'usage_date',
        'start_time',
        'end_time',
        'distance_traveled',
        'fuel_cost',
        'created_at'
    ];

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;
        
        $query = VehicleUsageLog::query()->with(['vehicle:id,vehicle_number,make,model', 'staff:id,full_name']);
        
        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);
        
        $this->applyUrlFilters($query, $request, [
            'vehicle_id', 'staff_id', 'usage_date', 'usage_type', 'is_approved', 'start_location', 'end_location'
        ]);
        
        $usageLogs = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $usageLogs]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'staff_id' => 'nullable|exists:staff,id',
            'assignment_id' => 'nullable|exists:vehicle_assignments,id',
            'usage_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'start_mileage' => 'required|integer|min:0',
            'end_mileage' => 'nullable|integer|min:0|gte:start_mileage',
            'distance_traveled' => 'nullable|integer|min:0',
            'purpose' => 'nullable|string|max:200',
            'route_description' => 'nullable|string|max:500',
            'start_location' => 'nullable|string|max:200',
            'end_location' => 'nullable|string|max:200',
            'fuel_consumed' => 'nullable|numeric|min:0',
            'fuel_cost' => 'nullable|numeric|min:0',
            'fuel_type' => 'nullable|string|max:20',
            'usage_type' => 'in:official,personal,mixed',
            'notes' => 'nullable|string',
            'is_approved' => 'boolean',
            'approved_by' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Calculate distance if end_mileage provided
        if ($request->has('end_mileage') && $request->end_mileage) {
            $request->merge(['distance_traveled' => $request->end_mileage - $request->start_mileage]);
        }

        $usageLog = VehicleUsageLog::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Vehicle usage log created successfully',
            'data' => $usageLog->load(['vehicle:id,vehicle_number,make,model', 'staff:id,full_name'])
        ], 201);
    }

    public function show(VehicleUsageLog $vehicleUsageLog)
    {
        return response()->json([
            'success' => true,
            'data' => $vehicleUsageLog->load(['vehicle:id,vehicle_number,make,model', 'staff:id,full_name'])
        ]);
    }

    public function update(Request $request, VehicleUsageLog $vehicleUsageLog)
    {
        $validator = Validator::make($request->all(), [
            'usage_date' => 'sometimes|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'start_mileage' => 'sometimes|integer|min:0',
            'end_mileage' => 'nullable|integer|min:0|gte:start_mileage',
            'distance_traveled' => 'nullable|integer|min:0',
            'purpose' => 'nullable|string|max:200',
            'route_description' => 'nullable|string|max:500',
            'start_location' => 'nullable|string|max:200',
            'end_location' => 'nullable|string|max:200',
            'fuel_consumed' => 'nullable|numeric|min:0',
            'fuel_cost' => 'nullable|numeric|min:0',
            'fuel_type' => 'nullable|string|max:20',
            'usage_type' => 'sometimes|in:official,personal,mixed',
            'notes' => 'nullable|string',
            'is_approved' => 'boolean',
            'approved_by' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Calculate distance if end_mileage provided
        if ($request->has('end_mileage') && $request->end_mileage) {
            $request->merge(['distance_traveled' => $request->end_mileage - ($request->start_mileage ?? $vehicleUsageLog->start_mileage)]);
        }

        $vehicleUsageLog->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Vehicle usage log updated successfully',
            'data' => $vehicleUsageLog->load(['vehicle:id,vehicle_number,make,model', 'staff:id,full_name'])
        ]);
    }

    public function destroy(VehicleUsageLog $vehicleUsageLog)
    {
        $vehicleUsageLog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle usage log deleted successfully'
        ]);
    }
}