<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\PropertyRequestRecord;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RequestAssignmentService
{
    // Called inside the request's transaction, after locking the request record.
    public function assign(
        PropertyRequestRecord $request,
        int $propertyId,
        User $issuer,
        ?array $unitIds = null,
        ?int $quantity = null,
        ?string $itemName = null,
    ): Assignment {
        $property = Property::query()->lockForUpdate()->findOrFail($propertyId);
        $quantity = $quantity ?? $request->requested_quantity;
        if ($quantity < 1 || $property->quantity < $quantity || in_array($property->status, [Property::STATUS_DISPOSED, Property::STATUS_FOR_DISPOSAL], true)) {
            throw ValidationException::withMessages(['property_id' => 'This property does not have enough assignable stock. Choose another property or mark the request as awaiting stock.']);
        }
        $recipient = $request->requester;
        if (! $recipient || ! $recipient->is_active || ! $recipient->isTeacher()) {
            throw ValidationException::withMessages(['status' => 'The requester must be an active end-user.']);
        }

        $assignment = Assignment::create([
            'property_id' => $property->id,
            'teacher_id' => $recipient->id,
            'teacher_profile_id' => $recipient->teacherProfile?->id,
            'assigned_by' => $issuer->id,
            'staff_id' => $issuer->staffProfile?->id,
            'quantity_assigned' => $quantity,
            'date_assigned' => today(),
            'assigned_at' => today(),
            'location' => $property->location,
            'department' => $property->department,
            'location_id' => $property->location_id,
            'remarks' => 'Approved property request '.$request->reference_number.': '.($itemName ?: $request->requested_item_name),
            'status' => Assignment::STATUS_ACTIVE,
        ]);
        app(InventoryUnitService::class)->assign($property, $assignment, $quantity, $unitIds);
        $property->decrement('quantity', $quantity);
        $property->refresh()->syncInventoryStatus();
        PropertyHistory::create([
            'property_id' => $property->id, 'action_type' => 'assigned',
            'reference' => 'assignment:'.$assignment->id, 'action_date' => today(),
            'remarks' => $assignment->remarks,
        ]);

        return $assignment;
    }
}
