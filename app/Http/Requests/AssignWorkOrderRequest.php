<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkOrderRequest extends FormRequest
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
            'assigned_to_id' => 'required_without:assigned_vendor_id|nullable|exists:staff,id',
            'assigned_vendor_id' => 'required_without:assigned_to_id|nullable|exists:vendors,id',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
