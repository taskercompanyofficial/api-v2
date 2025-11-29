<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StaffTraining;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffTrainingController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = StaffTraining::query()->with(['staff:id,full_name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'staff_id', 'training_type', 'training_category', 'status', 
            'completion_status', 'is_mandatory', 'expiry_date'
        ]);

        $training = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $training]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'training_title' => 'required|string|max:200',
            'training_provider' => 'required|string|max:200',
            'training_type' => 'required|string|max:50',
            'training_category' => 'nullable|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration_hours' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:200',
            'instructor_name' => 'nullable|string|max:100',
            'cost' => 'nullable|numeric|min:0',
            'currency' => 'string|max:3',
            'status' => 'in:scheduled,in_progress,completed,cancelled,postponed',
            'completion_status' => 'in:pending,passed,failed,incomplete',
            'score' => 'nullable|numeric|between:0,100',
            'certificate_file' => 'nullable|string|max:255',
            'is_mandatory' => 'boolean',
            'expiry_date' => 'nullable|date|after_or_equal:end_date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $training = StaffTraining::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff training record created successfully',
            'data' => $training->load(['staff:id,full_name'])
        ], 201);
    }

    public function show(StaffTraining $staffTraining)
    {
        return response()->json([
            'success' => true,
            'data' => $staffTraining->load(['staff:id,full_name'])
        ]);
    }

    public function update(Request $request, StaffTraining $staffTraining)
    {
        $validator = Validator::make($request->all(), [
            'training_title' => 'sometimes|string|max:200',
            'training_provider' => 'sometimes|string|max:200',
            'training_type' => 'sometimes|string|max:50',
            'training_category' => 'nullable|string|max:100',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration_hours' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:200',
            'instructor_name' => 'nullable|string|max:100',
            'cost' => 'nullable|numeric|min:0',
            'currency' => 'string|max:3',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled,postponed',
            'completion_status' => 'sometimes|in:pending,passed,failed,incomplete',
            'score' => 'nullable|numeric|between:0,100',
            'certificate_file' => 'nullable|string|max:255',
            'is_mandatory' => 'boolean',
            'expiry_date' => 'nullable|date|after_or_equal:end_date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $staffTraining->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff training record updated successfully',
            'data' => $staffTraining->load(['staff:id,full_name'])
        ]);
    }

    public function destroy(StaffTraining $staffTraining)
    {
        $staffTraining->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff training record deleted successfully'
        ]);
    }
}