<?php

namespace App\Http\Controllers\Authenticated\Vendor;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    /**
     * Resolve the vendor ID from the authenticated user.
     */
    protected function resolveVendorId(Request $request): int
    {
        $user = $request->user();
        if ($user instanceof \App\Models\VendorStaff) {
            return $user->vendor_id;
        }
        return $user->id;
    }

    /**
     * Get vendor earnings summary and transactions using the Ledger System
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $vendorId = $this->resolveVendorId($request);

            // 1. Get all ledger entries for this vendor
            $ledgerEntries = \App\Models\VendorLedger::where('vendor_id', $vendorId)
                ->with('workOrder:id,work_order_number')
                ->orderBy('transaction_date', 'desc')
                ->get();

            // 2. Calculate Summary from Ledger
            $totalCredit = $ledgerEntries->where('type', 'credit')->sum('amount');
            $totalDebit = $ledgerEntries->where('type', 'debit')->sum('amount');
            
            // This month's data
            $thisMonthEntries = $ledgerEntries->filter(function($entry) {
                return $entry->transaction_date->month == now()->month && 
                       $entry->transaction_date->year == now()->year;
            });
            $thisMonthRevenue = $thisMonthEntries->where('type', 'credit')->sum('amount');

            // Find current balance (from latest entry)
            $latestEntry = $ledgerEntries->first();
            $netBalance = $latestEntry ? (float)$latestEntry->running_balance : 0.00;

            // 3. Jobs performance (Basic stats from WorkOrders)
            $completedJobsCount = WorkOrder::where('assigned_vendor_id', $vendorId)
                ->whereNotNull('completed_at')
                ->count();
            
            $activeJobsCount = WorkOrder::where('assigned_vendor_id', $vendorId)
                ->whereNotNull('accepted_at')
                ->whereNull('completed_at')
                ->whereNull('cancelled_at')
                ->count();

            // 4. Map Ledger to Frontend Transaction format
            $transactions = $ledgerEntries->map(function ($entry) {
                return [
                    'id' => 'ledger_' . $entry->id,
                    'type' => $entry->type,
                    'title' => $entry->description,
                    'amount' => (float)$entry->amount,
                    'date' => $entry->transaction_date->toIso8601String(),
                    'category' => $entry->category,
                    'work_order_number' => $entry->workOrder?->work_order_number,
                    'status' => 'completed',
                    'running_balance' => (float)$entry->running_balance,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total_completed_jobs' => $completedJobsCount,
                        'total_service_revenue' => round($totalCredit, 2),
                        'this_month_jobs' => $thisMonthEntries->where('category', 'installation_fee')->count(),
                        'this_month_revenue' => round($thisMonthRevenue, 2),
                        'active_jobs' => $activeJobsCount,
                        'net_balance' => round($netBalance, 2),
                        'total_paid' => round($totalDebit, 2), // Debits represent payouts or adjustments
                    ],
                    'transactions' => $transactions,
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
