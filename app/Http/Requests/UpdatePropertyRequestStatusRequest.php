<?php

namespace App\Http\Requests;

use App\Models\PropertyRequestRecord;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, [User::ROLE_STAFF, User::ROLE_ADMIN], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in($this->user()->isStaff()
                ? [PropertyRequestRecord::STATUS_REVIEWED, PropertyRequestRecord::STATUS_FULFILLED]
                : [PropertyRequestRecord::STATUS_APPROVED, PropertyRequestRecord::STATUS_REJECTED, PropertyRequestRecord::STATUS_AWAITING_STOCK])],
            'response_notes' => ['required_if:status,rejected,awaiting_stock', 'nullable', 'string', 'max:3000'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'department' => ['nullable', 'string', 'max:255'],
            'serial_numbers' => ['prohibited'],
            'unit_ids' => ['sometimes', 'array', 'max:1000'],
            'unit_ids.*' => ['required', 'integer', 'distinct'],
            'select_units' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'items.*.department' => ['nullable', 'string', 'max:255'],
            'items.*.unit_ids' => ['sometimes', 'array', 'max:1000'],
            'items.*.unit_ids.*' => ['required', 'integer', 'distinct'],
            'items.*.select_units' => ['sometimes', 'boolean'],
        ];
    }
}
