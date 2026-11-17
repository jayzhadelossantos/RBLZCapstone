<?php

namespace App\Http\Requests;

use App\Models\PropertyRequestRecord;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequestSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_TEACHER;
    }

    public function rules(): array
    {
        return [
            'request_type' => ['nullable', Rule::in(PropertyRequestRecord::requestTypes())],
            'request_source' => ['nullable', Rule::in(['inventory', 'unavailable'])],
            'available_property_id' => ['required_if:request_source,inventory', 'nullable', 'integer', 'exists:properties,id'],
            'requested_item_name' => ['required_without_all:items,available_property_id', 'nullable', 'string', 'max:255'],
            'requested_quantity' => ['required_without:items', 'nullable', 'integer', 'min:1'],
            'items' => ['nullable', 'array', 'min:1', 'max:50'],
            'items.*.property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'items.*.inventory_id' => ['nullable', 'integer', 'exists:inventories,id'],
            'items.*.requested_item_name' => ['nullable', 'string', 'max:255'],
            'items.*.requested_quantity' => ['required_with:items', 'integer', 'min:1', 'max:100000'],
            'items.*.source' => ['nullable', Rule::in(['inventory', 'unavailable'])],
            'needed_by' => ['nullable', 'date'],
            'purpose' => ['required', 'string'],
            'additional_notes' => ['nullable', 'string'],
            'replacement_assignment_id' => ['required_if:request_type,replacement', 'nullable', 'integer', 'exists:assignments,id'],
            'issue_description' => ['required_if:request_type,replacement', 'nullable', 'string', 'max:3000'],
            'status' => ['nullable', Rule::in(PropertyRequestRecord::statuses())],
        ];
    }
}
