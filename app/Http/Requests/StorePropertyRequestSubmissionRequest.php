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
            'requested_item_name' => ['required_unless:request_source,inventory', 'nullable', 'string', 'max:255'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'needed_by' => ['nullable', 'date'],
            'purpose' => ['required', 'string'],
            'additional_notes' => ['nullable', 'string'],
            'replacement_assignment_id' => ['required_if:request_type,replacement', 'nullable', 'integer', 'exists:assignments,id'],
            'issue_description' => ['required_if:request_type,replacement', 'nullable', 'string', 'max:3000'],
            'status' => ['nullable', Rule::in(PropertyRequestRecord::statuses())],
        ];
    }
}
