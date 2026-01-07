<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\FileRequirementRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FileRequirementRuleController extends Controller
{
    /**
     * Display a listing of file requirement rules
     */
    public function index(Request $request)
    {
        $query = FileRequirementRule::with([
            'parentService',
            'serviceConcern',
            'serviceSubConcern',
            'authorizedBrand',
            'category',
            'fileType'
        ]);

        // Filter by requirement type
        if ($request->filled('requirement_type')) {
            $query->where('requirement_type', $request->requirement_type);
        }

        // Filter by file type
        if ($request->filled('file_type_id')) {
            $query->where('file_type_id', $request->file_type_id);
        }

        // Filter by parent service
        if ($request->filled('parent_service_id')) {
            $query->where('parent_service_id', $request->parent_service_id);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sort
        $sortBy = $request->get('sortBy', 'priority');
        $sortOrder = $request->get('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->get('perPage', 15);
        $rules = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $rules,
        ]);
    }

    /**
     * Store a newly created file requirement rule
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_service_id' => 'nullable|exists:parent_services,id',
            'service_concern_id' => 'nullable|exists:service_concerns,id',
            'service_sub_concern_id' => 'nullable|exists:service_sub_concerns,id',
            'is_warranty_case' => 'nullable|boolean',
            'authorized_brand_id' => 'nullable|exists:authorized_brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'file_type_id' => 'required|exists:file_types,id',
            'requirement_type' => 'required|in:required,optional,hidden',
            'required_if_field' => 'nullable|string|max:255',
            'required_if_value' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:0',
            'help_text' => 'nullable|string',
            'validation_rules' => 'nullable|json',
            'priority' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'boolean',
        ]);

        // Set defaults
        $validated['display_order'] = $validated['display_order'] ?? 0;
        $validated['priority'] = $validated['priority'] ?? 50;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $rule = FileRequirementRule::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'File requirement rule created successfully',
            'data' => $rule->load([
                'parentService',
                'serviceConcern',
                'serviceSubConcern',
                'authorizedBrand',
                'category',
                'fileType'
            ]),
        ], 201);
    }

    /**
     * Display the specified file requirement rule
     */
    public function show($id)
    {
        $rule = FileRequirementRule::with([
            'parentService',
            'serviceConcern',
            'serviceSubConcern',
            'authorizedBrand',
            'category',
            'fileType'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $rule,
        ]);
    }

    /**
     * Update the specified file requirement rule
     */
    public function update(Request $request, $id)
    {
        $rule = FileRequirementRule::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'parent_service_id' => 'nullable|exists:parent_services,id',
            'service_concern_id' => 'nullable|exists:service_concerns,id',
            'service_sub_concern_id' => 'nullable|exists:service_sub_concerns,id',
            'is_warranty_case' => 'nullable|boolean',
            'authorized_brand_id' => 'nullable|exists:authorized_brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'file_type_id' => 'sometimes|required|exists:file_types,id',
            'requirement_type' => 'sometimes|required|in:required,optional,hidden',
            'required_if_field' => 'nullable|string|max:255',
            'required_if_value' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:0',
            'help_text' => 'nullable|string',
            'validation_rules' => 'nullable|json',
            'priority' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'boolean',
        ]);

        $rule->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'File requirement rule updated successfully',
            'data' => $rule->load([
                'parentService',
                'serviceConcern',
                'serviceSubConcern',
                'authorizedBrand',
                'category',
                'fileType'
            ]),
        ]);
    }

    /**
     * Remove the specified file requirement rule
     */
    public function destroy($id)
    {
        $rule = FileRequirementRule::findOrFail($id);
        $rule->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'File requirement rule deleted successfully',
        ]);
    }
}
