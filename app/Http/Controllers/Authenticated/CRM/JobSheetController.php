<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Response;

class JobSheetController extends Controller
{
    /**
     * Generate and download job sheet PDF for a work order
     */
    public function generate(string $id): Response
    {
        // Load work order with all necessary relationships
        $workOrder = WorkOrder::with([
            'customer',
            'address',
            'brand',
            'branch',
            'category',
            'service',
            'parentService',
            'product',
            'status',
            'subStatus',
            'assignedTo',
            'services',
        ])->findOrFail($id);

        // Generate PDF from Blade view
        $pdf = Pdf::loadView('job-sheet', [
            'workOrder' => $workOrder,
            'generatedDate' => now()->format('d/m/Y H:i'),
        ]);

        // Set paper size and orientation
        $pdf->setPaper('a4', 'portrait');

        // Download PDF with work order number in filename
        return $pdf->download("JobSheet_{$workOrder->work_order_number}.pdf");
    }

    /**
     * Stream PDF in browser (for preview)
     */
    public function preview(string $id): Response
    {
        $workOrder = WorkOrder::with([
            'customer',
            'address',
            'brand',
            'branch',
            'category',
            'service',
            'parentService',
            'product',
            'status',
            'subStatus',
            'assignedTo',
            'services',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('job-sheet', [
            'workOrder' => $workOrder,
            'generatedDate' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        // Stream PDF in browser
        return $pdf->stream("JobSheet_{$workOrder->work_order_number}.pdf");
    }

    /**
     * Test PDF generation with sample data
     */
    public function test(): Response
    {
        $pdf = Pdf::loadView('test-pdf');
        $pdf->setPaper('a4', 'portrait');
        return $pdf->download("test.pdf");
    }
}
