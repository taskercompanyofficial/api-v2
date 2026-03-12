<?php

namespace App\Services\Vendor;

use App\Models\WorkOrder;
use App\Models\VendorLedger;
use App\Models\DefaultVendorLedger;
use App\Models\VendorSpecificRate;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorLedgerService
{
    /**
     * Process a work order and create ledger entries based on the profit-sharing model.
     * This follows the logic:
     * 1. Installation Fees (Credit)
     * 2. Profit Sharing on Parts (Credit)
     * 3. Cash Adjustment if vendor collected/held money (Debit)
     */
    public function processWorkOrder(WorkOrder $workOrder): void
    {
        DB::transaction(function () use ($workOrder) {
            $vendorId = $workOrder->assigned_vendor_id;
            if (!$vendorId) return;

            // Prevent double processing for the same work order
            $exists = VendorLedger::where('work_order_id', $workOrder->id)
                ->whereIn('category', ['installation_fee', 'profit_share'])
                ->exists();
            
            if ($exists) {
                Log::info("Work Order #{$workOrder->id} already processed for ledger. Skipping.");
                return;
            }

            // Fetch current balance
            $lastEntry = VendorLedger::where('vendor_id', $vendorId)
                ->orderBy('id', 'desc')
                ->first();
            $runningBalance = $lastEntry ? $lastEntry->running_balance : 0;

            // 1. Process Installation Fees
            $this->processInstallationFees($workOrder, $vendorId, $runningBalance);

            // 2. Process Profit Sharing
            $this->processProfitSharing($workOrder, $vendorId, $runningBalance);
        });
    }

    private function processInstallationFees(WorkOrder $workOrder, int $vendorId, &$runningBalance): void
    {
        $specificRates = VendorSpecificRate::where('vendor_id', $vendorId)
            ->where('item_type', 'service')
            ->where('is_active', true)
            ->get()
            ->keyBy('parent_service_id');

        $defaultRates = DefaultVendorLedger::services()
            ->where('is_active', true)
            ->get()
            ->keyBy('parent_service_id');

        $woServices = $workOrder->services;

        // Fallback: If no explicit services are added, use the parent_service_id from the work order itself
        if ($woServices->isEmpty() && $workOrder->parent_service_id) {
            $woServices = collect([(object)[
                'parent_service_id' => $workOrder->parent_service_id,
                'service_name' => $workOrder->parentService->name ?? 'Installation Service'
            ]]);
        }

        foreach ($woServices as $woService) {
            $rate = 0;
            $specific = $specificRates->get($woService->parent_service_id);
            $default = $defaultRates->get($woService->parent_service_id);

            if ($specific && !is_null($specific->vendor_rate)) {
                $rate = (float)$specific->vendor_rate;
            } elseif ($default) {
                $rate = (float)$default->vendor_rate;
            }

            if ($rate > 0) {
                $runningBalance += $rate;
                VendorLedger::create([
                    'vendor_id' => $vendorId,
                    'work_order_id' => $workOrder->id,
                    'type' => 'credit',
                    'amount' => $rate,
                    'running_balance' => $runningBalance,
                    'category' => 'installation_fee',
                    'description' => "Installation Fee: " . ($woService->service_name ?? 'Service'),
                    'transaction_date' => now(),
                ]);
            }
        }
    }

    private function processProfitSharing(WorkOrder $workOrder, int $vendorId, &$runningBalance): void
    {
        $specificRates = VendorSpecificRate::where('vendor_id', $vendorId)
            ->where('item_type', 'part')
            ->where('is_active', true)
            ->get()
            ->keyBy('part_code');

        $defaultRates = DefaultVendorLedger::parts()
            ->where('is_active', true)
            ->get()
            ->keyBy('part_code');

        $receivedBy = $workOrder->payment_received_by ?? 'vendor';

        foreach ($workOrder->workOrderParts as $woPart) {
            $partCode = $woPart->part ? $woPart->part->part_number : null;
            if (!$partCode) continue;

            $default = $defaultRates->get($partCode);
            if (!$default) continue;

            $specific = $specificRates->get($partCode);

            // Fetch sharing percentage (Default 50%)
            $sharePercent = 50.00;
            if ($specific && !is_null($specific->revenue_share_percentage)) {
                $sharePercent = (float)$specific->revenue_share_percentage;
            } elseif ($default) {
                $sharePercent = (float)$default->revenue_share_percentage;
            }

            $costPrice = (float)$default->cost_price;
            $sellingPrice = (float)$woPart->unit_price;
            $profitPerUnit = $sellingPrice - $costPrice;
            $vendorSharePerUnit = ($profitPerUnit * ($sharePercent / 100));
            $companySharePerUnit = $profitPerUnit - $vendorSharePerUnit;

            $totalVendorShare = $vendorSharePerUnit * (int)$woPart->quantity;
            $totalCompanyShare = $companySharePerUnit * (int)$woPart->quantity;

            if ($receivedBy === 'company') {
                // Scenario: Company has the money. Company owes vendor his share.
                if ($totalVendorShare > 0) {
                    $runningBalance += $totalVendorShare;
                    VendorLedger::create([
                        'vendor_id' => $vendorId,
                        'work_order_id' => $workOrder->id,
                        'type' => 'credit',
                        'amount' => $totalVendorShare,
                        'running_balance' => $runningBalance,
                        'category' => 'profit_share',
                        'description' => "Profit Share (Credit) for " . $woPart->part->name . " (Direct Payment to Company)",
                        'transaction_date' => now(),
                    ]);
                }
            } else {
                // Scenario: Vendor has the money. Company must deduct its share from what we owe vendor.
                // Math per prompt: deduct company share from installation credit.
                if ($totalCompanyShare > 0) {
                    $runningBalance -= $totalCompanyShare;
                    VendorLedger::create([
                        'vendor_id' => $vendorId,
                        'work_order_id' => $workOrder->id,
                        'type' => 'debit',
                        'amount' => $totalCompanyShare,
                        'running_balance' => $runningBalance,
                        'category' => 'profit_share_adjustment',
                        'description' => "Profit Share Adjustment (Debit) for " . $woPart->part->name . " (Vendor held cash)",
                        'transaction_date' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Record a manual payment sent to the vendor.
     */
    public function recordPayout(int $vendorId, float $amount, string $reference = ''): void
    {
        DB::transaction(function () use ($vendorId, $amount, $reference) {
            $lastEntry = VendorLedger::where('vendor_id', $vendorId)
                ->orderBy('id', 'desc')
                ->first();
            $newBalance = ($lastEntry ? $lastEntry->running_balance : 0) - $amount;

            VendorLedger::create([
                'vendor_id' => $vendorId,
                'type' => 'debit',
                'amount' => $amount,
                'running_balance' => $newBalance,
                'category' => 'payout',
                'description' => "Payment payout to vendor" . ($reference ? " (Ref: $reference)" : ""),
                'transaction_date' => now(),
            ]);
        });
    }
}
