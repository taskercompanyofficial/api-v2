<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = Vehicle::query();

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'vehicle_number', 'registration_number', 'make', 'model', 'year',
            'vehicle_category', 'status', 'fuel_type', 'registration_expiry', 'insurance_expiry'
        ]);

        $vehicles = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $vehicles]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_number' => 'required|string|max:50|unique:vehicles,vehicle_number',
            'registration_number' => 'required|string|max:50|unique:vehicles,registration_number',
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . date('Y'),
            'color' => 'nullable|string|max:50',
            'engine_number' => 'nullable|string|max:100',
            'chassis_number' => 'nullable|string|max:100',
            'fuel_type' => 'nullable|string|max:20',
            'transmission_type' => 'nullable|string|max:20',
            'seating_capacity' => 'nullable|integer|min:1',
            'vehicle_category' => 'nullable|string|max:50',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'purchase_vendor' => 'nullable|string|max:200',
            'registration_date' => 'nullable|date',
            'registration_expiry' => 'nullable|date|after_or_equal:registration_date',
            'insurance_policy_number' => 'nullable|string|max:100',
            'insurance_expiry' => 'nullable|date',
            'insurance_provider' => 'nullable|string|max:100',
            'current_mileage' => 'nullable|integer|min:0',
            'status' => 'in:available,assigned,maintenance,retired,damaged,sold',
            'location' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'photo_file' => 'nullable|string|max:255',
            'registration_file' => 'nullable|string|max:255',
            'insurance_file' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $vehicle = Vehicle::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Vehicle created successfully',
            'data' => $vehicle
        ], 201);
    }

    public function show(Vehicle $vehicle)
    {
        return response()->json([
            'success' => true,
            'data' => $vehicle
        ]);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_number' => 'sometimes|string|max:50|unique:vehicles,vehicle_number,' . $vehicle->id,
            'registration_number' => 'sometimes|string|max:50|unique:vehicles,registration_number,' . $vehicle->id,
            'make' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'sometimes|integer|min:1900|max:' . date('Y'),
            'color' => 'nullable|string|max:50',
            'engine_number' => 'nullable|string|max:100',
            'chassis_number' => 'nullable|string|max:100',
            'fuel_type' => 'nullable|string|max:20',
            'transmission_type' => 'nullable|string|max:20',
            'seating_capacity' => 'nullable|integer|min:1',
            'vehicle_category' => 'nullable|string|max:50',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'purchase_vendor' => 'nullable|string|max:200',
            'registration_date' => 'nullable|date',
            'registration_expiry' => 'nullable|date|after_or_equal:registration_date',
            'insurance_policy_number' => 'nullable|string|max:100',
            'insurance_expiry' => 'nullable|date',
            'insurance_provider' => 'nullable|string|max:100',
            'current_mileage' => 'nullable|integer|min:0',
            'status' => 'sometimes|in:available,assigned,maintenance,retired,damaged,sold',
            'location' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'photo_file' => 'nullable|string|max:255',
            'registration_file' => 'nullable|string|max:255',
            'insurance_file' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $vehicle->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle
        ]);
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted successfully'
        ]);
    }
}