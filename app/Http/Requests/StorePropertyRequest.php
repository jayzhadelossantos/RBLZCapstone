<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'property_name' => ['required', 'string', 'max:255'],
            'property_code' => ['required', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('properties', 'serial_number')],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
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
            'qr_token' => ['nullable', 'string', 'max:255'],
            'qr_code_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $propertyCode = trim((string) $this->input('property_code'));
            $qrToken = trim((string) $this->input('qr_token'));

            if ($propertyCode === '') {
                $validator->errors()->add('property_code', 'Property code is required.');
                return;
            }

            if (Property::query()->where('property_code', $propertyCode)->exists()) {
                $validator->errors()->add(
                    'property_code',
                    'This property code already exists.'
                );
            }

            if ($qrToken === '') {
                return;
            }

            if (Property::query()->where('qr_token', $qrToken)->exists()) {
                $validator->errors()->add(
                    'qr_token',
                    'This QR token already exists.'
                );
            }
        });
    }
}
