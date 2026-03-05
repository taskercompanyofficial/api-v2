<?php

namespace App\Jobs;

use App\Models\WorkOrder;
use App\Models\WorkOrderHistory;
use App\Models\WorkOrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReopenScheduledWorkOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $workOrderId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(int $workOrderId)
    {
        $this->workOrderId = $workOrderId;
    }

    /**
     * Execute the job.
     * Changes the work order status from "Scheduled" to "Allocated" when the scheduled date arrives.
     */
    public function handle(): void
    {
        $workOrder = WorkOrder::find($this->workOrderId);

        if (!$workOrder) {
            Log::warning("ReopenScheduledWorkOrderJob: Work order #{$this->workOrderId} not found.");
            return;
        }

        // Only proceed if the work order is still in "Scheduled" status
        $scheduledStatus = WorkOrderStatus::where('slug', 'scheduled')
            ->whereNull('parent_id')
            ->first();

        if (!$scheduledStatus) {
            Log::error("ReopenScheduledWorkOrderJob: 'Scheduled' status not found in database.");
            return;
        }

        // If the work order is no longer in "Scheduled" status, skip (it may have been manually changed)
        if ($workOrder->status_id !== $scheduledStatus->id) {
            Log::info("ReopenScheduledWorkOrderJob: Work order #{$this->workOrderId} is no longer in 'Scheduled' status (current status_id: {$workOrder->status_id}). Skipping.");
            return;
        }

        // Find the "Allocated" status
        $allocatedStatus = WorkOrderStatus::where('slug', 'allocated')
            ->whereNull('parent_id')
            ->first();

        if (!$allocatedStatus) {
            Log::error("ReopenScheduledWorkOrderJob: 'Allocated' status not found in database.");
            return;
        }

        $oldStatusId = $workOrder->status_id;
        $oldSubStatusId = $workOrder->sub_status_id;

        // Update work order status to "Allocated"
        $workOrder->status_id = $allocatedStatus->id;
        $workOrder->sub_status_id = null;
        $workOrder->save();

        // Log the automatic status change in history
        WorkOrderHistory::create([
            'work_order_id' => $workOrder->id,
            'action_type' => 'auto_reopened',
            'description' => "Work order automatically reopened from Scheduled to Allocated (scheduled date reached)",
            'metadata' => json_encode([
                'old_status_id' => $oldStatusId,
                'new_status_id' => $allocatedStatus->id,
                'old_sub_status_id' => $oldSubStatusId,
                'appointment_date' => $workOrder->appointment_date,
                'appointment_time' => $workOrder->appointment_time,
                'triggered_by' => 'system_job',
            ]),
            'user_name' => 'System',
        ]);

        Log::info("ReopenScheduledWorkOrderJob: Work order #{$this->workOrderId} status changed from Scheduled to Allocated.");
    }
}
