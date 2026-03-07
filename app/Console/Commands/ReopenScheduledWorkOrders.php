<?php

namespace App\Console\Commands;

use App\Models\WorkOrder;
use App\Models\WorkOrderHistory;
use App\Models\WorkOrderStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReopenScheduledWorkOrders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'work-orders:reopen-scheduled';

    /**
     * The console command description.
     */
    protected $description = 'Automatically reopen work orders whose scheduled date/time has been reached';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $scheduledStatus = WorkOrderStatus::where('slug', 'scheduled')
            ->whereNull('parent_id')
            ->first();

        if (!$scheduledStatus) {
            $this->warn('No "Scheduled" status found in the database. Skipping.');
            return 0;
        }

        $allocatedStatus = WorkOrderStatus::where('slug', 'allocated')
            ->whereNull('parent_id')
            ->first();

        if (!$allocatedStatus) {
            $this->error('No "Allocated" status found in the database.');
            return 1;
        }

        // Find all work orders with "Scheduled" status whose appointment date has arrived or passed
        $workOrders = WorkOrder::where('status_id', $scheduledStatus->id)
            ->whereNotNull('appointment_date')
            ->where('appointment_date', '<=', Carbon::today()->toDateString())
            ->get();

        if ($workOrders->isEmpty()) {
            $this->info('No scheduled work orders to reopen.');
            return 0;
        }

        $count = 0;

        foreach ($workOrders as $workOrder) {
            $oldStatusId = $workOrder->status_id;
            $oldSubStatusId = $workOrder->sub_status_id;

            $workOrder->status_id = $allocatedStatus->id;
            $workOrder->sub_status_id = null;
            $workOrder->save();

            // Log in history
            WorkOrderHistory::create([
                'work_order_id' => $workOrder->id,
                'action_type' => 'auto_reopened',
                'description' => "Work order automatically reopened from Scheduled to Allocated (scheduled date reached: {$workOrder->appointment_date} {$workOrder->appointment_time})",
                'metadata' => json_encode([
                    'old_status_id' => $oldStatusId,
                    'new_status_id' => $allocatedStatus->id,
                    'old_sub_status_id' => $oldSubStatusId,
                    'appointment_date' => $workOrder->appointment_date,
                    'appointment_time' => $workOrder->appointment_time,
                    'triggered_by' => 'scheduled_command',
                ]),
                'user_name' => 'System',
            ]);

            $count++;
            $this->line("  ✓ Work order #{$workOrder->id} ({$workOrder->work_order_number}) reopened.");
        }

        $this->info("Done! {$count} work order(s) reopened from Scheduled to Allocated.");
        Log::info("ReopenScheduledWorkOrders command: {$count} work order(s) reopened.");

        return 0;
    }
}
