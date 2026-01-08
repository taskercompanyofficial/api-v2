<?php

use Illuminate\Support\Facades\Route;
use App\Models\WorkOrder;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-pdf', function () {
    return view('test-pdf');
});
Route::get('/job-sheet/{workOrderId}', function ($workOrderId) {
    $workOrder = WorkOrder::with([
        'customer',
        'address',
        'city',
        'brand',
        'category',
        'service',
        'product',
        'status',
        'subStatus',
        'branch',
        'assignedTo',
        'services',
    ])->findOrFail($workOrderId);

    return view('job-sheet', [
        'workOrder' => $workOrder,
        'generatedDate' => now()->format('d/m/Y')
    ]);
});
