<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Services\FileRequirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileRequirementController extends Controller
{
    protected FileRequirementService $fileRequirementService;

    public function __construct(FileRequirementService $fileRequirementService)
    {
        $this->fileRequirementService = $fileRequirementService;
    }

    /**
     * Get file requirements for given context
     * This endpoint returns dynamic file requirements based on user's selections
     */
    public function getRequirements(Request $request): JsonResponse
    {
        $context = $request->validate([
            'parent_service_id' => 'nullable|exists:parent_services,id',
            'service_concern_id' => 'nullable|exists:service_concerns,id',
            'service_sub_concern_id' => 'nullable|exists:service_sub_concerns,id',
            'is_warranty_case' => 'nullable|boolean',
            'authorized_brand_id' => 'nullable|exists:authorized_brands,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $requirements = $this->fileRequirementService->getRequirementsForContext($context);

        return response()->json([
            'status' => 'success',
            'data' => $requirements,
            'count' => count($requirements)
        ]);
    }

    /**
     * Validate uploaded files against context requirements
     */
    public function validateFiles(Request $request): JsonResponse
    {
        $request->validate([
            'context' => 'required|array',
            'uploaded_files' => 'required|array',
            'uploaded_files.*.file_type_id' => 'required|exists:file_types,id'
        ]);

        $context = $request->input('context');
        $uploadedFiles = $request->input('uploaded_files');

        $validation = $this->fileRequirementService->validateFileUpload($context, $uploadedFiles);

        return response()->json([
            'status' => $validation['valid'] ? 'success' : 'error',
            'data' => $validation
        ], $validation['valid'] ? 200 : 422);
    }
}
