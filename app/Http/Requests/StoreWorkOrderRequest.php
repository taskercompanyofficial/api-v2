<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
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
            'customer_id' => 'required|exists:customers,id',
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'city_id' => 'required|exists:cities,id',
            'category_id' => 'required|exists:categories,id',
            'service_id' => 'required|exists:services,id',
            'parent_service_id' => 'required|exists:parent_services,id',
            'service_concern_id' => 'required|exists:service_concerns,id',
            'service_sub_concern_id' => 'required|exists:service_sub_concerns,id',
            'warranty_type_id' => 'required|exists:warranty_types,id',
            'customer_description' => 'required|string|min:10',
            'authorized_brand_id' => 'required|exists:authorized_brands,id',
            'brand_complaint_no' => 'nullable|string|max:100',
            'priority' => 'required|in:low,medium,high',
            'dealer_id' => 'nullable|exists:dealers,id',
            'dealer_branch_id' => 'nullable|exists:dealer_branches,id',
            'reference' => 'nullable|string|max:100',
            'extra_number' => 'nullable|string|max:100',
        ];
    }
}
