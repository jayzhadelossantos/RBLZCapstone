<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequestSubmissionRequest;
use App\Http\Requests\UpdatePropertyRequestStatusRequest;
use App\Models\Assignment;
use App\Models\Inventory;
use App\Models\Property;
use App\Models\PropertyRequestRecord;
use App\Models\User;
use App\Services\RequestAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyRequestController extends Controller
{
    public function teacherIndex(Request $request): View
    {
        $propertyRequests = PropertyRequestRecord::query()
            ->with(['processedBy', 'reviewedBy'])
            ->where('requested_by', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('teacher.property-requests.index', [
            'propertyRequests' => $propertyRequests,
            'statuses' => PropertyRequestRecord::statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        $selectedInventory = Inventory::query()
            ->with('property')
            ->where('available_quantity', '>', 0)
            ->find($request->integer('inventory_id'));

        return view('teacher.property-requests.create', [
            'propertyRequest' => new PropertyRequestRecord,
            'assignedProperties' => $this->activeAssignmentsFor(auth()->user()->id),
            'availableProperties' => $this->availableProperties(),
            'selectedInventory' => $selectedInventory,
        ]);
    }

    public function store(StorePropertyRequestSubmissionRequest $request): RedirectResponse
    {
        $type = $request->input('request_type', PropertyRequestRecord::TYPE_NEW);
        $replacementAssignment = null;
        $availableProperty = null;
        $inventoryId = null;

        if ($type === PropertyRequestRecord::TYPE_REPLACEMENT) {
            $replacementAssignment = $this->activeAssignmentsFor($request->user()->id)
                ->firstWhere('id', $request->integer('replacement_assignment_id'));

            if (! $replacementAssignment) {
                throw ValidationException::withMessages(['replacement_assignment_id' => 'Select an active property assigned to you.']);
            }

            if ($request->integer('requested_quantity') > $replacementAssignment->quantity_assigned) {
                throw ValidationException::withMessages(['requested_quantity' => 'Replacement quantity cannot exceed the quantity assigned to you.']);
            }
        } elseif ($request->input('request_source') === 'inventory') {
            $inventory = Inventory::query()->with('property')
                ->where('property_id', $request->integer('available_property_id'))
                ->where('available_quantity', '>', 0)
                ->first();
            $availableProperty = $inventory?->property;
            $inventoryId = $inventory?->id;

            if (! $availableProperty) {
                throw ValidationException::withMessages(['available_property_id' => 'Select an available inventory item, or choose the unavailable-item request option.']);
            }

            if ($request->integer('requested_quantity') > $inventory->available_quantity) {
                throw ValidationException::withMessages(['requested_quantity' => 'Requested quantity exceeds the available inventory quantity.']);
            }
        }

        $requestedItemName = $replacementAssignment?->property?->property_name
            ?? $availableProperty?->property_name
            ?? trim((string) $request->input('requested_item_name'));

        $hasDuplicateRequest = PropertyRequestRecord::query()
            ->where('requested_by', $request->user()->id)
            ->whereIn('status', [
                PropertyRequestRecord::STATUS_PENDING,
                PropertyRequestRecord::STATUS_REVIEWED,
                PropertyRequestRecord::STATUS_AWAITING_STOCK,
                PropertyRequestRecord::STATUS_APPROVED,
            ])
            ->whereRaw('LOWER(requested_item_name) = ?', [mb_strtolower($requestedItemName)])
            ->exists();

        if ($hasDuplicateRequest) {
            throw ValidationException::withMessages([
                'requested_item_name' => 'You already have an active request for this item. Please wait for it to be processed before submitting another request.',
            ]);
        }

        DB::transaction(function () use ($request, $type, $requestedItemName, $replacementAssignment, $inventoryId) {
            if ($inventoryId) {
                $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
                if ($inventory->available_quantity < $request->integer('requested_quantity')) {
                    throw ValidationException::withMessages(['requested_quantity' => 'Requested quantity exceeds the available inventory quantity.']);
                }
                $inventory->decrement('available_quantity', $request->integer('requested_quantity'));
            }

            PropertyRequestRecord::query()->create([
                'requested_by' => $request->user()->id,
                'request_type' => $type,
                'requested_item_name' => $requestedItemName,
                'requested_quantity' => $request->integer('requested_quantity'),
                'needed_by' => $request->date('needed_by'),
                'purpose' => trim((string) $request->input('purpose')),
                'additional_notes' => $this->normalizeText($request->input('additional_notes')),
                'issue_description' => $type === PropertyRequestRecord::TYPE_REPLACEMENT ? $this->normalizeText($request->input('issue_description')) : null,
                'replacement_assignment_id' => $replacementAssignment?->id,
                'inventory_id' => $inventoryId,
                'status' => PropertyRequestRecord::STATUS_PENDING,
            ]);
        });

        return redirect()
            ->route('teacher.property-requests.index')
            ->with('success', 'Property request submitted successfully.');
    }

    public function index(Request $request): View
    {
        $propertyRequests = PropertyRequestRecord::query()
            ->with(['requester', 'processedBy', 'selectedProperty', 'replacementAssignment.property', 'inventory.property'])
            ->when($request->user()->isAdmin(), fn ($query) => $query->whereNotNull('reviewed_at'))
            ->when(in_array($request->query('status'), PropertyRequestRecord::statuses(), true), fn ($query) => $query->where('status', $request->query('status')))
            ->latest()
            ->paginate(10)->withQueryString();

        return view('property-requests.index', [
            'propertyRequests' => $propertyRequests,
            'statuses' => PropertyRequestRecord::statuses(),
        ]);
    }

    public function show(Request $request, PropertyRequestRecord $propertyRequest): View
    {
        abort_if($request->user()->isAdmin() && ! $propertyRequest->reviewed_at, 403);
        $propertyRequest->load(['requester', 'processedBy', 'reviewedBy', 'assignment.property', 'selectedProperty', 'replacementAssignment.property', 'inventory.property']);

        return view('property-requests.show', [
            'propertyRequest' => $propertyRequest,
            'statuses' => PropertyRequestRecord::statuses(),
            'properties' => Property::query()->with('availableUnits')->where('quantity', '>=', max(1, $propertyRequest->requested_quantity))
                ->when($propertyRequest->inventory_id, fn ($query) => $query->where('id', $propertyRequest->inventory?->property_id))
                ->when($request->user()->isStaff() && $propertyRequest->status === PropertyRequestRecord::STATUS_APPROVED,
                    fn ($query) => $query->where('id', $propertyRequest->selected_property_id))
                ->whereNotIn('status', [Property::STATUS_DISPOSED, Property::STATUS_FOR_DISPOSAL])
                ->orderBy('property_name')->get(),
        ]);
    }

    public function updateStatus(
        UpdatePropertyRequestStatusRequest $request,
        PropertyRequestRecord $propertyRequest
    ): RedirectResponse {
        DB::transaction(function () use ($request, $propertyRequest) {
            $record = PropertyRequestRecord::query()->lockForUpdate()->findOrFail($propertyRequest->id);
            if ($request->user()->isStaff()) {
                if ($request->input('status') === PropertyRequestRecord::STATUS_FULFILLED) {
                    if ($record->status !== PropertyRequestRecord::STATUS_APPROVED || ! $record->reviewed_at || ! $record->processed_at || $record->assignment_id) {
                        throw ValidationException::withMessages(['status' => 'The admin must approve this request before staff can assign a property.']);
                    }
                    if ($record->selected_property_id !== $request->integer('property_id')) {
                        throw ValidationException::withMessages(['property_id' => 'Select the property approved by the admin for this request.']);
                    }
                    $assignment = app(RequestAssignmentService::class)->assign($record, $request->integer('property_id'), $request->user(),
                        $request->boolean('select_units') || $request->has('unit_ids') ? $request->input('unit_ids', []) : null);
                    $assignment->update(['department' => $request->input('department') ?: $assignment->department]);
                    $record->update(['status' => PropertyRequestRecord::STATUS_FULFILLED, 'assignment_id' => $assignment->id, 'selected_property_id' => $assignment->property_id]);

                    return;
                }
                if ($record->status !== PropertyRequestRecord::STATUS_PENDING) {
                    throw ValidationException::withMessages(['status' => 'This request has already been forwarded or processed.']);
                }
                $record->update([
                    'status' => PropertyRequestRecord::STATUS_REVIEWED,
                    'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
                    'review_notes' => $this->normalizeText($request->input('response_notes')),
                ]);

                return;
            }
            if (! $record->reviewed_at || $record->assignment_id || ! in_array($record->status, [PropertyRequestRecord::STATUS_REVIEWED, PropertyRequestRecord::STATUS_AWAITING_STOCK, PropertyRequestRecord::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages(['status' => 'Only requests forwarded by staff and awaiting a decision can be processed.']);
            }
            if ($request->input('status') === PropertyRequestRecord::STATUS_APPROVED) {
                $propertyId = $record->inventory?->property_id ?: $request->integer('property_id');
                $property = Property::query()->lockForUpdate()->findOrFail($propertyId);
                if ($property->quantity < $record->requested_quantity || in_array($property->status, [Property::STATUS_DISPOSED, Property::STATUS_FOR_DISPOSAL], true)) {
                    throw ValidationException::withMessages(['property_id' => 'Not enough available stock. Keep this request awaiting stock until inventory is available.']);
                }
            }
            if ($request->input('status') === PropertyRequestRecord::STATUS_REJECTED && $record->inventory_id) {
                Inventory::query()->lockForUpdate()->find($record->inventory_id)?->increment('available_quantity', $record->requested_quantity);
            }
            $record->update([
                'status' => $request->input('status'),
                'selected_property_id' => $request->input('status') === PropertyRequestRecord::STATUS_APPROVED ? ($record->inventory?->property_id ?: $request->integer('property_id')) : null,
                'response_notes' => $this->normalizeText($request->input('response_notes')),
                'processed_by' => $request->user()->id, 'processed_at' => now(),
            ]);
        });

        return redirect()
            ->route('property-requests.show', $propertyRequest)
            ->with('success', match ($request->input('status')) {
                PropertyRequestRecord::STATUS_REVIEWED => 'Confirmation saved successfully. Request forwarded to the admin for approval.',
                PropertyRequestRecord::STATUS_APPROVED => 'Confirmation saved successfully. Request approved for staff assignment.',
                PropertyRequestRecord::STATUS_FULFILLED => 'Property assigned successfully. The receiving receipt is ready to print.',
                default => 'Confirmation saved successfully. Request status updated.',
            });
    }

    public function receipt(Request $request, PropertyRequestRecord $propertyRequest): View
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isStaff() || $propertyRequest->requested_by === $request->user()->id, 403);
        abort_unless($propertyRequest->assignment_id, 404);
        $assignment = $propertyRequest->assignment()->with(['property', 'propertyUnits', 'assignee', 'assignedBy'])->firstOrFail();
        $admins = User::query()->where('role', User::ROLE_ADMIN)->where('is_active', true)->orderBy('name')->get();
        $activeAdmin = $admins->firstWhere('id', auth()->id()) ?? User::defaultAdmin() ?? $admins->first();
        $approver = $propertyRequest->processedBy ?? $activeAdmin;

        return view('assignments.print', [
            'assignment' => $assignment,
            'printedAt' => now(),
            'approvedBy' => $approver,
            'admins' => $admins,
            'verifyingAdmin' => $approver ?? $assignment->assignedBy,
            'requestNumber' => $propertyRequest->id,
            'backUrl' => $request->user()->isTeacher() ? route('teacher.property-requests.index') : route('property-requests.show', $propertyRequest),
        ]);
    }

    private function normalizeText(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function activeAssignmentsFor(int $userId)
    {
        return Assignment::query()
            ->with('property')
            ->where('teacher_id', $userId)
            ->where('status', Assignment::STATUS_ACTIVE)
            ->orderByDesc('assigned_at')
            ->get();
    }

    private function availableProperties()
    {
        return Inventory::query()->with('property')
            ->where('available_quantity', '>', 0)
            ->whereHas('property', fn ($query) => $query->whereNotIn('status', [Property::STATUS_DISPOSED, Property::STATUS_FOR_DISPOSAL]))
            ->get()
            ->map(function (Inventory $inventory) {
                $property = $inventory->property;
                $property->setAttribute('inventory_available_quantity', $inventory->available_quantity);

                return $property;
            })
            ->sortBy('property_name')->values();
    }
}
