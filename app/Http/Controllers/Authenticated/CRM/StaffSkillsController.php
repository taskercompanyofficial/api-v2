<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StaffSkill;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffSkillsController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = StaffSkill::query()->with(['staff:id,full_name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'staff_id', 'skill_name', 'skill_category', 'proficiency_level', 
            'is_certified', 'is_primary_skill'
        ]);

        $skills = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $skills]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'skill_name' => 'required|string|max:100',
            'skill_category' => 'required|string|max:50',
            'proficiency_level' => 'required|in:beginner,intermediate,advanced,expert',
            'years_of_experience' => 'nullable|integer|min:0',
            'last_used_date' => 'nullable|date',
            'is_certified' => 'boolean',
            'certification_body' => 'nullable|string|max:100',
            'certification_date' => 'nullable|date',
            'certification_expiry' => 'nullable|date|after_or_equal:certification_date',
            'description' => 'nullable|string',
            'is_primary_skill' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $skill = StaffSkill::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff skill created successfully',
            'data' => $skill->load(['staff:id,full_name'])
        ], 201);
    }

    public function show(StaffSkill $staffSkill)
    {
        return response()->json([
            'success' => true,
            'data' => $staffSkill->load(['staff:id,full_name'])
        ]);
    }

    public function update(Request $request, StaffSkill $staffSkill)
    {
        $validator = Validator::make($request->all(), [
            'skill_name' => 'sometimes|string|max:100',
            'skill_category' => 'sometimes|string|max:50',
            'proficiency_level' => 'sometimes|in:beginner,intermediate,advanced,expert',
            'years_of_experience' => 'nullable|integer|min:0',
            'last_used_date' => 'nullable|date',
            'is_certified' => 'boolean',
            'certification_body' => 'nullable|string|max:100',
            'certification_date' => 'nullable|date',
            'certification_expiry' => 'nullable|date|after_or_equal:certification_date',
            'description' => 'nullable|string',
            'is_primary_skill' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $staffSkill->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff skill updated successfully',
            'data' => $staffSkill->load(['staff:id,full_name'])
        ]);
    }

    public function destroy(StaffSkill $staffSkill)
    {
        $staffSkill->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff skill deleted successfully'
        ]);
    }
}