<?php

namespace App\Http\Controllers\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\FileType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FileTypeController extends Controller
{
    /**
     * Display a listing of file types
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('perPage', 15);
        $search = $request->input('search');
        $status = $request->input('status');
        $category = $request->input('category'); // image, document, video, audio

        $query = FileType::query();

        // Search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status !== null) {
            $query->where('status', $status === 'true' || $status === '1');
        }

        // Filter by category
        if ($category) {
            switch ($category) {
                case 'image':
                    $query->images();
                    break;
                case 'document':
                    $query->documents();
                    break;
                case 'video':
                    $query->videos();
                    break;
                case 'audio':
                    $query->audios();
                    break;
            }
        }

        $fileTypes = $query->ordered()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $fileTypes,
        ]);
    }

    /**
     * Store a newly created file type
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:file_types,name',
            'slug' => 'nullable|string|max:255|unique:file_types,slug',
            'description' => 'nullable|string|max:500',
            'max_file_size' => 'required|integer|min:1',
            'mime_types' => 'nullable|array',
            'mime_types.*' => 'string',
            'extensions' => 'nullable|array',
            'extensions.*' => 'string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'is_image' => 'boolean',
            'is_document' => 'boolean',
            'is_video' => 'boolean',
            'is_audio' => 'boolean',
            'status' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Auto-generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $fileType = FileType::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'File type created successfully',
            'data' => $fileType,
        ], 201);
    }

    /**
     * Display the specified file type
     */
    public function show(string $id): JsonResponse
    {
        $fileType = FileType::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $fileType,
        ]);
    }

    /**
     * Update the specified file type
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $fileType = FileType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:file_types,name,' . $id,
            'slug' => 'nullable|string|max:255|unique:file_types,slug,' . $id,
            'description' => 'nullable|string|max:500',
            'max_file_size' => 'required|integer|min:1',
            'mime_types' => 'nullable|array',
            'mime_types.*' => 'string',
            'extensions' => 'nullable|array',
            'extensions.*' => 'string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'is_image' => 'boolean',
            'is_document' => 'boolean',
            'is_video' => 'boolean',
            'is_audio' => 'boolean',
            'status' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Auto-generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $fileType->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'File type updated successfully',
            'data' => $fileType,
        ]);
    }

    /**
     * Remove the specified file type
     */
    public function destroy(string $id): JsonResponse
    {
        $fileType = FileType::findOrFail($id);
        $fileType->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'File type deleted successfully',
        ]);
    }

    /**
     * Restore a soft-deleted file type
     */
    public function restore(string $id): JsonResponse
    {
        $fileType = FileType::withTrashed()->findOrFail($id);
        $fileType->restore();

        return response()->json([
            'status' => 'success',
            'message' => 'File type restored successfully',
            'data' => $fileType,
        ]);
    }

    /**
     * Toggle file type status
     */
    public function toggleStatus(string $id): JsonResponse
    {
        $fileType = FileType::findOrFail($id);
        $fileType->status = !$fileType->status;
        $fileType->save();

        return response()->json([
            'status' => 'success',
            'message' => 'File type status updated successfully',
            'data' => $fileType,
        ]);
    }

    /**
     * Validate if a file matches the file type constraints
     */
    public function validateFile(Request $request, string $id): JsonResponse
    {
        $fileType = FileType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'mime_type' => 'required|string',
            'extension' => 'required|string',
            'file_size' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mimeType = $request->input('mime_type');
        $extension = $request->input('extension');
        $fileSize = $request->input('file_size');

        $errors = [];

        // Check MIME type
        if (!$fileType->acceptsMimeType($mimeType)) {
            $errors[] = "MIME type '{$mimeType}' is not allowed";
        }

        // Check extension
        if (!$fileType->acceptsExtension($extension)) {
            $errors[] = "Extension '{$extension}' is not allowed";
        }

        // Check file size
        if ($fileSize > $fileType->max_file_size) {
            $errors[] = "File size exceeds maximum allowed size of {$fileType->formatted_file_size}";
        }

        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File validation failed',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'File is valid',
        ]);
    }

    /**
     * Get file types in raw format for SearchSelect component
     */
    public function fileTypesRaw(Request $request): JsonResponse
    {
        try {
            $searchQuery = $request->input('name');

            $query = FileType::query()->where('status', true);

            if ($searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $fileTypes = $query->select('id', 'name', 'description', 'icon')
                ->orderBy('sort_order', 'asc')
                ->limit(50)
                ->get()
                ->map(function ($fileType) {
                    return [
                        'value' => $fileType->id,
                        'label' => $fileType->name,
                        'description' => $fileType->description,
                        'badge' => $fileType->icon,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $fileTypes,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve file types.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
