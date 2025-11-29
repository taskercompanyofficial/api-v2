<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StaffAsset;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffAssetsController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = StaffAsset::query()->with(['staff:id,full_name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'staff_id', 'asset_type', 'asset_name', 'asset_tag', 
            'status', 'assigned_date', 'is_returnable'
        ]);

        $assets = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $assets]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'asset_type' => 'required|string|max:50',
            'asset_name' => 'required|string|max:200',
            'asset_model' => 'nullable|string|max:100',
            'asset_serial' => 'nullable|string|max:100',
            'asset_tag' => 'nullable|string|max:50|unique:staff_assets,asset_tag',
            'manufacturer' => 'nullable|string|max:100',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'vendor' => 'nullable|string|max:100',
            'condition' => 'string|max:20',
            'status' => 'in:assigned,available,maintenance,retired,lost,damaged',
            'assigned_date' => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:assigned_date',
            'assigned_by' => 'nullable|string|max:100',
            'assignment_notes' => 'nullable|string',
            'photo_file' => 'nullable|string|max:255',
            'receipt_file' => 'nullable|string|max:255',
            'warranty_expiry' => 'nullable|date',
            'is_returnable' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $asset = StaffAsset::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff asset created successfully',
            'data' => $asset->load(['staff:id,full_name'])
        ], 201);
    }

    public function show(StaffAsset $staffAsset)
    {
        return response()->json([
            'success' => true,
            'data' => $staffAsset->load(['staff:id,full_name'])
        ]);
    }

    public function update(Request $request, StaffAsset $staffAsset)
    {
        $validator = Validator::make($request->all(), [
            'asset_type' => 'sometimes|string|max:50',
            'asset_name' => 'sometimes|string|max:200',
            'asset_model' => 'nullable|string|max:100',
            'asset_serial' => 'nullable|string|max:100',
            'asset_tag' => 'nullable|string|max:50|unique:staff_assets,asset_tag,' . $staffAsset->id,
            'manufacturer' => 'nullable|string|max:100',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'vendor' => 'nullable|string|max:100',
            'condition' => 'string|max:20',
            'status' => 'sometimes|in:assigned,available,maintenance,retired,lost,damaged',
            'assigned_date' => 'sometimes|date',
            'expected_return_date' => 'nullable|date|after_or_equal:assigned_date',
            'assigned_by' => 'nullable|string|max:100',
            'assignment_notes' => 'nullable|string',
            'photo_file' => 'nullable|string|max:255',
            'receipt_file' => 'nullable|string|max:255',
            'warranty_expiry' => 'nullable|date',
            'is_returnable' => 'boolean',
            'return_date' => 'nullable|date',
            'return_condition' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $staffAsset->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Staff asset updated successfully',
            'data' => $staffAsset->load(['staff:id,full_name'])
        ]);
    }

    public function destroy(StaffAsset $staffAsset)
    {
        $staffAsset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff asset deleted successfully'
        ]);
    }
}