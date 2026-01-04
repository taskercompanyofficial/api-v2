<?php

namespace App\Services\WorkOrder;

use App\Models\WorkOrder;
use App\Models\CustomerFeedback;
use Exception;

class WorkOrderFeedbackService
{
    /**
     * Get all feedback for a work order
     */
    public function getFeedback(WorkOrder $workOrder)
    {
        return $workOrder->customerFeedbacks()->with('staff:id,first_name,last_name')->latest()->get();
    }

    /**
     * Add customer feedback
     */
    public function addFeedback(WorkOrder $workOrder, array $data, int $userId): CustomerFeedback
    {
        return $workOrder->customerFeedbacks()->create([
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'staff_id' => $userId,
        ]);
    }

    /**
     * Delete customer feedback
     */
    public function deleteFeedback(CustomerFeedback $feedback): bool
    {
        return $feedback->delete();
    }
}
