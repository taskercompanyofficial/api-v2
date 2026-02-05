<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\DefaultVendorLedger;
use App\Models\ParentService;
use App\Traits\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DefaultVendorLedgerController extends Controller
{
    use QueryFilterTrait;

    /**
     * Get all default vendor ledger entries
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10);
        $itemType = $request->input('item_type'); // 'service' or 'part'

        $query = DefaultVendorLedger::query()
            ->with(['parentService:id,name', 'creator:id,first_name,last_name', 'updater:id,first_name,last_name']);

        // Filter by item type
        if ($itemType && in_array($itemType, ['service', 'part'])) {
            $query->where('item_type', $itemType);
        }

        // Apply filters
        $this->applyJsonFilters($query, $request);

        // Apply sorting
        if ($request->has('sort')) {
            $this->applySorting($query, $request);
        } else {
            $query->orderBy('item_type')->orderBy('created_at', 'desc');
        }

        $ledger = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $ledger->items(),
            'pagination' => [
                'current_page' => $ledger->currentPage(),
                'last_page' => $ledger->lastPage(),
                'per_page' => $ledger->perPage(),
                'total' => $ledger->total(),
            ],
        ]);
    }

    /**
     * Get services for dropdown
     */
    public function getServices()
    {
        $services = DefaultVendorLedger::active()
            ->services()
            ->with('parentService:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->service_name,
                    'parent_service_id' => $item->parent_service_id,
                    'parent_service_name' => $item->parentService?->name,
                    'vendor_rate' => $item->vendor_rate,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $services,
        ]);
    }

    /**
     * Get parts for dropdown
     */
    public function getParts()
    {
        $parts = DefaultVendorLedger::active()
            ->parts()
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->part_name,
                    'code' => $item->part_code,
                    'unit' => $item->unit,
                    'cost_price' => $item->cost_price,
                    'revenue_share_percentage' => $item->revenue_share_percentage,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $parts,
        ]);
    }

    /**
     * Get a single ledger entry
     */
    public function show($id)
    {
        $entry = DefaultVendorLedger::with(['parentService', 'creator', 'updater'])->find($id);

        if (!$entry) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entry not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $entry,
        ]);
    }

    /**
     * Create a new ledger entry
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), DefaultVendorLedger::validationRules());

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = Auth::id();

        // If service, get service name from parent service
        if ($data['item_type'] === 'service' && isset($data['parent_service_id'])) {
            $parentService = \App\Models\ParentServices::find($data['parent_service_id']);
            if ($parentService) {
                $data['service_name'] = $parentService->name;
            }
        }

        // Generate part code if not provided
        if ($data['item_type'] === 'part' && empty($data['part_code'])) {
            $data['part_code'] = $this->generatePartCode($data['part_name']);
        }

        $entry = DefaultVendorLedger::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Entry created successfully',
            'data' => $entry->load(['parentService', 'creator']),
        ], 201);
    }

    /**
     * Update a ledger entry
     */
    public function update(Request $request, $id)
    {
        $entry = DefaultVendorLedger::find($id);

        if (!$entry) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entry not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), DefaultVendorLedger::validationRules($id));

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = Auth::id();

        // If service, update service name from parent service
        if ($data['item_type'] === 'service' && isset($data['parent_service_id'])) {
            $parentService = \App\Models\ParentServices::find($data['parent_service_id']);
            if ($parentService) {
                $data['service_name'] = $parentService->name;
            }
        }

        $entry->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Entry updated successfully',
            'data' => $entry->load(['parentService', 'updater']),
        ]);
    }

    /**
     * Delete a ledger entry
     */
    public function destroy($id)
    {
        $entry = DefaultVendorLedger::find($id);

        if (!$entry) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entry not found',
            ], 404);
        }

        $entry->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Entry deleted successfully',
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleStatus($id)
    {
        $entry = DefaultVendorLedger::find($id);

        if (!$entry) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entry not found',
            ], 404);
        }

        $entry->is_active = !$entry->is_active;
        $entry->updated_by = Auth::id();
        $entry->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated successfully',
            'data' => $entry,
        ]);
    }

    /**
     * Bulk update revenue share percentage for parts
     */
    public function bulkUpdateRevenueShare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:default_vendor_ledger,id',
            'revenue_share_percentage' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = DefaultVendorLedger::whereIn('id', $request->ids)
            ->where('item_type', 'part')
            ->update([
                'revenue_share_percentage' => $request->revenue_share_percentage,
                'updated_by' => Auth::id(),
            ]);

        return response()->json([
            'status' => 'success',
            'message' => "{$updated} entries updated successfully",
        ]);
    }

    /**
     * Generate unique part code
     */
    private function generatePartCode($partName)
    {
        // Take first 4 letters, convert to uppercase, remove spaces
        $prefix = strtoupper(substr(preg_replace('/\s+/', '', $partName), 0, 4));

        // Find the highest number for this prefix
        $lastEntry = DefaultVendorLedger::where('part_code', 'LIKE', $prefix . '%')
            ->orderBy('part_code', 'desc')
            ->first();

        if ($lastEntry && preg_match('/' . $prefix . '(\d+)/', $lastEntry->part_code, $matches)) {
            $number = intval($matches[1]) + 1;
        } else {
            $number = 1;
        }

        return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get summary statistics
     */
    public function getSummary()
    {
        $totalServices = DefaultVendorLedger::services()->active()->count();
        $totalParts = DefaultVendorLedger::parts()->active()->count();
        $inactiveCount = DefaultVendorLedger::where('is_active', false)->count();

        $avgServiceRate = DefaultVendorLedger::services()->active()->avg('vendor_rate');
        $avgPartCost = DefaultVendorLedger::parts()->active()->avg('cost_price');
        $avgRevenueShare = DefaultVendorLedger::parts()->active()->avg('revenue_share_percentage');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_services' => $totalServices,
                'total_parts' => $totalParts,
                'inactive_count' => $inactiveCount,
                'avg_service_rate' => round($avgServiceRate ?? 0, 2),
                'avg_part_cost' => round($avgPartCost ?? 0, 2),
                'avg_revenue_share' => round($avgRevenueShare ?? 50, 2),
            ],
        ]);
    }
}
