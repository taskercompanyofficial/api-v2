<?php

namespace App\Http\Controllers\Authenticated\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DefaultVendorLedger;
use App\Models\VendorSpecificRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorRateController extends Controller
{
    /**
     * Get the rates and profit-sharing percentages assigned to this vendor.
     * This merges global defaults with vendor-specific overrides.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $vendorId = ($user instanceof \App\Models\VendorStaff) ? $user->vendor_id : $user->id;

            // 1. Get Global Defaults
            $defaultServices = DefaultVendorLedger::services()
                ->where('is_active', true)
                ->with('parentService:id,name')
                ->get();

            $defaultParts = DefaultVendorLedger::parts()
                ->where('is_active', true)
                ->get();

            // 2. Get Vendor Specific Overrides
            $specificRates = VendorSpecificRate::where('vendor_id', $vendorId)
                ->where('is_active', true)
                ->with('parentService:id,name')
                ->get();

            $specificServices = $specificRates->where('item_type', 'service')->keyBy('parent_service_id');
            $specificParts = $specificRates->where('item_type', 'part')->keyBy('part_code');

            // 3. Compile Service Ledger
            $serviceLedger = collect();
            
            // Start with defaults
            foreach ($defaultServices as $default) {
                $specific = $specificServices->get($default->parent_service_id);
                $serviceLedger->push([
                    'id' => $default->id,
                    'service_id' => $default->parent_service_id,
                    'name' => $default->service_name ?? $default->parentService?->name ?? 'Unknown Service',
                    'base_rate' => (float)$default->vendor_rate,
                    'base_share' => (float)$default->revenue_share_percentage,
                    'is_overridden' => $specific !== null,
                    'assigned_rate' => $specific ? (float)$specific->vendor_rate : (float)$default->vendor_rate,
                    'assigned_share' => $specific ? (float)$specific->revenue_share_percentage : (float)$default->revenue_share_percentage,
                ]);
            }

            // Add specific services NOT in defaults
            $defaultServiceIds = $defaultServices->pluck('parent_service_id')->toArray();
            foreach ($specificServices as $specific) {
                if (!in_array($specific->parent_service_id, $defaultServiceIds)) {
                    $serviceLedger->push([
                        'id' => $specific->id,
                        'service_id' => $specific->parent_service_id,
                        'name' => $specific->parentService?->name ?? 'Unknown Service',
                        'base_rate' => 0,
                        'base_share' => 0,
                        'is_overridden' => true,
                        'assigned_rate' => (float)$specific->vendor_rate,
                        'assigned_share' => (float)$specific->revenue_share_percentage,
                    ]);
                }
            }

            // 4. Compile Parts Ledger
            $partsLedger = collect();
            
            // Start with defaults
            foreach ($defaultParts as $default) {
                $specific = $specificParts->get($default->part_code);
                $partsLedger->push([
                    'id' => $default->id,
                    'part_code' => $default->part_code,
                    'name' => $default->part_name ?? 'Unknown Part',
                    'base_share' => (float)$default->revenue_share_percentage,
                    'is_overridden' => $specific !== null,
                    'assigned_share' => $specific ? (float)$specific->revenue_share_percentage : (float)$default->revenue_share_percentage,
                ]);
            }

            // Add specific parts NOT in defaults
            $defaultPartCodes = $defaultParts->pluck('part_code')->toArray();
            foreach ($specificParts as $specific) {
                if (!in_array($specific->part_code, $defaultPartCodes)) {
                    $partsLedger->push([
                        'id' => $specific->id,
                        'part_code' => $specific->part_code,
                        'name' => $specific->part_name ?? 'Specific Part',
                        'base_share' => 0,
                        'is_overridden' => true,
                        'assigned_share' => (float)$specific->revenue_share_percentage,
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'services' => $serviceLedger->values(),
                    'parts' => $partsLedger->values(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
