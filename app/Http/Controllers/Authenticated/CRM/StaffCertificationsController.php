<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StaffCertification;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffCertificationsController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = StaffCertification::query()->with(['staff:id,full_name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'staff_id', 'certification_name', 'issuing_organization', 
            'status', 'is_verified', 'expiry_date'
        ]);

        $certifications = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $certifications]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'certification_name' => 'required|string|max:200',
            'issuing_organization' => 'required|string|max:200',
            'certification_number' => 'nullable|string|max:100',
            'issue_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'has_expiry' => 'boolean',
            'credential_url' => 'nullable|url|max:255',
            'certificate_file' => 'nullable|string|max:255',
            'is_verified' => 'boolean',
            'verified_date' => 'nullable|date',
            'verified_by' => 'nullable|string|max:100',
            'status' => 'in:active,expired,revoked,pending',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $certification = StaffCertification::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff certification created successfully',
            'data' => $certification->load(['staff:id,full_name'])
        ], 201);
    }

    public function show(StaffCertification $staffCertification)
    {
        return response()->json([
            'success' => true,
            'data' => $staffCertification->load(['staff:id,full_name'])
        ]);
    }

    public function update(Request $request, StaffCertification $staffCertification)
    {
        $validator = Validator::make($request->all(), [
            'certification_name' => 'sometimes|string|max:200',
            'issuing_organization' => 'sometimes|string|max:200',
            'certification_number' => 'nullable|string|max:100',
            'issue_date' => 'sometimes|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'has_expiry' => 'boolean',
            'credential_url' => 'nullable|url|max:255',
            'certificate_file' => 'nullable|string|max:255',
            'is_verified' => 'boolean',
            'verified_date' => 'nullable|date',
            'verified_by' => 'nullable|string|max:100',
            'status' => 'sometimes|in:active,expired,revoked,pending',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $staffCertification->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff certification updated successfully',
            'data' => $staffCertification->load(['staff:id,full_name'])
        ]);
    }

    public function destroy(StaffCertification $staffCertification)
    {
        $staffCertification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff certification deleted successfully'
        ]);
    }
}