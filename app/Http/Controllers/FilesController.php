<?php

namespace App\Http\Controllers;

use App\Models\Files;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FilesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $files = Files::where('file_status', 'active')->get();
        return response()->json($files);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:2004800', // 200MB max
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $folder = $request->input('folder', 'uploads');
            
            // Generate unique filename
            $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            
            // Store file in public disk
            $filePath = $file->storeAs($folder, $fileName, 'public');
            
            // Create file record
            $fileRecord = Files::create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'file_mime_type' => $file->getMimeType(),
                'file_type' => $file->getClientMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'path' => $fileRecord->file_path,
                'url' => url($fileRecord->file_path),
                'file' => $fileRecord,
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => 'No file uploaded',
        ], 400);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $file = Files::findOrFail($id);
        return response()->json($file);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $file = Files::findOrFail($id);
        
        $request->validate([
            'file_description' => 'nullable|string',
            'file_status' => 'nullable|in:active,inactive',
        ]);

        $file->update($request->only(['file_description', 'file_status']));

        return response()->json([
            'success' => true,
            'message' => 'File updated successfully',
            'file' => $file,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $file = Files::findOrFail($id);
        
        // Delete physical file
        $filePath = str_replace('/storage/', '', $file->file_path);
        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
        
        // Delete database record
        $file->delete();

        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully',
        ]);
    }
}
