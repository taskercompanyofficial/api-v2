<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Work Order Details
            'brand_complaint_no' => 'nullable|string|max:100',
            'priority' => 'nullable|string|in:low,medium,high',
            'reject_reason' => 'nullable|string|max:1000',
            'satisfation_code' => 'nullable|string|max:50',
            'without_satisfaction_code_reason' => 'nullable|string|max:1000',
            
            // Descriptions
            'customer_description' => 'nullable|string',
            'defect_description' => 'nullable|string',
            'technician_remarks' => 'nullable|string',
            'service_description' => 'nullable|string',
            
            // Product Information
            'product_indoor_model' => 'nullable|string|max:100',
            'product_outdoor_model' => 'nullable|string|max:100',
            'indoor_serial_number' => 'nullable|string|max:100',
            'outdoor_serial_number' => 'nullable|string|max:100',
            'warrenty_serial_number' => 'nullable|string|max:100',
            'warrenty_status' => 'nullable|string|max:50',
            'warrenty_end_date' => 'nullable|date',
            
            // Foreign Keys
            'authorized_brand_id' => 'required|exists:authorized_brands,id',
            'branch_id' => 'required|exists:our_branches,id',
            'category_id' => 'required|exists:categories,id',
            'service_id' => 'required|exists:services,id',
            'parent_service_id' => 'required|exists:parent_services,id',
            'product_id' => 'required|exists:products,id',
        ];
    }
}
