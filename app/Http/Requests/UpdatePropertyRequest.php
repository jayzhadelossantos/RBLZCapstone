<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $propertyId = $this->route('property')?->id;

        return [
            'property_name' => ['required', 'string', 'max:255'],
            'property_code' => ['required', 'string', 'max:100', Rule::unique('properties', 'property_code')->ignore($propertyId)],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('properties', 'serial_number')->ignore($propertyId)],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000'],
            'units' => ['sometimes', 'array', 'max:1000'],
            'units.*' => ['array:id,serial_number'],
            'units.*.id' => ['nullable', 'integer', 'distinct'],
            'units.*.serial_number' => ['nullable', 'string', 'max:255'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', Rule::in(Property::measurementUnits())],
            'date_acquired' => ['nullable', 'date'],
            'condition_status' => ['required', Rule::in(Property::conditionStatuses())],
            'status' => ['required', Rule::in(Property::statuses())],
            'office' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'qr_token' => ['nullable', 'string', 'max:255', Rule::unique('properties', 'qr_token')->ignore($propertyId)],
            'qr_code_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
