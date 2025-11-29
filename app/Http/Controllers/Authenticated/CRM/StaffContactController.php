<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StaffContact;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffContactController extends Controller
{
    use QueryFilterTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = StaffContact::query()->with(['staff:id,full_name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'staff_id', 'contact_type', 'name', 'phone', 'email', 'is_primary'
        ]);

        $contacts = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $contacts]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'contact_type' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'relationship' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // If this is primary, unset other primary contacts for this staff
        if ($request->is_primary) {
            StaffContact::where('staff_id', $request->staff_id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $contact = StaffContact::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff contact created successfully',
            'data' => $contact->load(['staff:id,full_name'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(StaffContact $staffContact)
    {
        return response()->json([
            'success' => true,
            'data' => $staffContact->load(['staff:id,full_name'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StaffContact $staffContact)
    {
        $validator = Validator::make($request->all(), [
            'contact_type' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|max:100',
            'relationship' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // If setting as primary, unset other primary contacts for this staff
        if ($request->has('is_primary') && $request->is_primary) {
            StaffContact::where('staff_id', $staffContact->staff_id)
                ->where('is_primary', true)
                ->where('id', '!=', $staffContact->id)
                ->update(['is_primary' => false]);
        }

        $staffContact->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff contact updated successfully',
            'data' => $staffContact->load(['staff:id,full_name'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StaffContact $staffContact)
    {
        $staffContact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff contact deleted successfully'
        ]);
    }
}