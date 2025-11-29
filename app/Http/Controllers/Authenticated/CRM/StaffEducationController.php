<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StaffEducation;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffEducationController extends Controller
{
    use QueryFilterTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = StaffEducation::query()->with(['staff:id,full_name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'staff_id', 'education_level', 'institution_name', 'degree_title', 
            'field_of_study', 'is_completed', 'is_verified'
        ]);

        $education = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $education]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'institution_name' => 'required|string|max:200',
            'degree_title' => 'required|string|max:200',
            'field_of_study' => 'nullable|string|max:100',
            'education_level' => 'required|in:high_school,diploma,bachelor,master,phd,other',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_completed' => 'boolean',
            'gpa' => 'nullable|numeric|between:0,4',
            'grade' => 'nullable|string|max:20',
            'certificate_file' => 'nullable|string|max:255',
            'is_verified' => 'boolean',
            'verified_date' => 'nullable|date',
            'verified_by' => 'nullable|string|max:100',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $education = StaffEducation::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff education record created successfully',
            'data' => $education->load(['staff:id,full_name'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(StaffEducation $staffEducation)
    {
        return response()->json([
            'success' => true,
            'data' => $staffEducation->load(['staff:id,full_name'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StaffEducation $staffEducation)
    {
        $validator = Validator::make($request->all(), [
            'institution_name' => 'sometimes|string|max:200',
            'degree_title' => 'sometimes|string|max:200',
            'field_of_study' => 'nullable|string|max:100',
            'education_level' => 'sometimes|in:high_school,diploma,bachelor,master,phd,other',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_completed' => 'boolean',
            'gpa' => 'nullable|numeric|between:0,4',
            'grade' => 'nullable|string|max:20',
            'certificate_file' => 'nullable|string|max:255',
            'is_verified' => 'boolean',
            'verified_date' => 'nullable|date',
            'verified_by' => 'nullable|string|max:100',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $staffEducation->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff education record updated successfully',
            'data' => $staffEducation->load(['staff:id,full_name'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StaffEducation $staffEducation)
    {
        $staffEducation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff education record deleted successfully'
        ]);
    }
}