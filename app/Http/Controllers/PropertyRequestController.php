<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequestSubmissionRequest;
use App\Http\Requests\UpdatePropertyRequestStatusRequest;
use App\Models\Assignment;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Property;
use App\Models\PropertyRequestItem;
use App\Models\PropertyRequestRecord;
use App\Models\User;
use App\Services\RequestAssignmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyRequestController extends Controller
{
    public function teacherIndex(Request $request): View
    {
        $propertyRequests = PropertyRequestRecord::query()
            ->with(['processedBy', 'reviewedBy', 'items'])
            ->where('requested_by', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('teacher.property-requests.index', [
            'propertyRequests' => $propertyRequests,
            'statuses' => PropertyRequestRecord::statuses(),
        ]);
    }

    public function create(): View
    {
        $availableProperties = $this->availableProperties();

        return view('teacher.property-requests.create', [
            'propertyRequest' => new PropertyRequestRecord,
            'assignedProperties' => $this->activeAssignmentsFor(auth()->user()->id),
            'availableProperties' => $availableProperties,
            'availableCategories' => $availableProperties->pluck('category')->filter()->unique()->sort()->values(),
        ]);
    }

    public function store(StorePropertyRequestSubmissionRequest $request): RedirectResponse
    {
        $type = $request->input('request_type', PropertyRequestRecord::TYPE_NEW);
        $replacementAssignment = null;
        $itemRows = collect($request->input('items', []))->values();
        $isLegacyPayload = $itemRows->isEmpty();

        if ($type === PropertyRequestRecord::TYPE_REPLACEMENT) {
            $replacementAssignment = $this->activeAssignmentsFor($request->user()->id)
                ->firstWhere('id', $request->integer('replacement_assignment_id'));

            if (! $replacementAssignment) {
                throw ValidationException::withMessages(['replacement_assignment_id' => 'Select an active property assigned to you.']);
            }

            $replacementQuantity = $request->integer('requested_quantity') ?: (int) ($itemRows->first()['requested_quantity'] ?? 1);
            if ($replacementQuantity > $replacementAssignment->quantity_assigned) {
                throw ValidationException::withMessages(['requested_quantity' => 'Replacement quantity cannot exceed the quantity assigned to you.']);
            }

            $itemRows = collect([[
                'property_id' => null,
                'requested_item_name' => $replacementAssignment->property?->property_name,
                'requested_quantity' => $replacementQuantity,
                'source' => 'unavailable',
            ]]);
            $isLegacyPayload = true;
        } elseif ($itemRows->isEmpty()) {
            $itemRows = collect([[
                'property_id' => $request->input('available_property_id'),
                'requested_item_name' => $request->input('requested_item_name'),
                'requested_quantity' => $request->integer('requested_quantity'),
                'source' => $request->input('request_source') === 'inventory' || $request->filled('available_property_id') ? 'inventory' : 'unavailable',
            ]]);
        }

        $items = [];
        $seenPropertyIds = [];

        foreach ($itemRows as $index => $row) {
            $quantity = (int) ($row['requested_quantity'] ?? 0);
            $propertyId = ! empty($row['property_id']) ? (int) $row['property_id'] : null;
            $source = $row['source'] ?? ($propertyId ? 'inventory' : 'unavailable');
            $quantityKey = $isLegacyPayload ? 'requested_quantity' : 'items.'.$index.'.requested_quantity';
            $nameKey = $isLegacyPayload ? 'requested_item_name' : 'items.'.$index.'.requested_item_name';

            if ($quantity < 1) {
                throw ValidationException::withMessages([$quantityKey => 'Enter a quantity of at least 1.']);
            }

            if ($propertyId) {
                if (in_array($propertyId, $seenPropertyIds, true)) {
                    throw ValidationException::withMessages([$nameKey => 'This property is already selected in the request. Change its quantity instead of adding it twice.']);
                }
                $seenPropertyIds[] = $propertyId;

                $property = Property::query()->with('inventory')->find($propertyId);
                $inventory = $property?->inventory;
                if (! $property || ! $inventory) {
                    throw ValidationException::withMessages([$nameKey => 'Select an available inventory item, or choose the unavailable-item request option.']);
                }
                if (in_array($property->status, [Property::STATUS_DISPOSED, Property::STATUS_FOR_DISPOSAL], true)) {
                    throw ValidationException::withMessages([$nameKey => 'This property is not available for request.']);
                }
                if ($quantity > $inventory->available_quantity) {
                    throw ValidationException::withMessages([$quantityKey => 'Requested quantity exceeds the available inventory quantity of '.$inventory->available_quantity.'.']);
                }

                $name = $property->property_name;
                $source = 'inventory';
            } else {
                $name = trim((string) ($row['requested_item_name'] ?? ''));
                $inventory = null;
                if ($name === '') {
                    throw ValidationException::withMessages([$nameKey => 'Enter the name of the item you need.']);
                }
                $source = 'unavailable';
            }

            $hasDuplicateRequest = PropertyRequestRecord::query()
                ->where('requested_by', $request->user()->id)
                ->whereIn('status', [
                    PropertyRequestRecord::STATUS_PENDING,
                    PropertyRequestRecord::STATUS_REVIEWED,
                    PropertyRequestRecord::STATUS_AWAITING_STOCK,
                    PropertyRequestRecord::STATUS_APPROVED,
                ])
                ->whereRaw('LOWER(requested_item_name) = ?', [mb_strtolower($name)])
                ->exists();

            if ($hasDuplicateRequest) {
                throw ValidationException::withMessages([
                    $nameKey => 'You already have an active request for this item. Please wait for it to be processed before submitting another request.',
                ]);
            }

            $items[] = [
                'property_id' => $propertyId,
                'inventory_id' => $inventory?->id,
                'requested_item_name' => $name,
                'requested_quantity' => $quantity,
                'source' => $source,
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Select at least one property to request.']);
        }

        $summaryName = count($items) === 1
            ? $items[0]['requested_item_name']
            : $items[0]['requested_item_name'].' +'.(count($items) - 1).' more';
        $totalQuantity = array_sum(array_column($items, 'requested_quantity'));

        DB::transaction(function () use ($request, $type, $items, $summaryName, $totalQuantity, $replacementAssignment) {
            $reservedInventories = [];

            foreach ($items as $index => $item) {
                if (! $item['inventory_id']) {
                    continue;
                }

                $inventory = Inventory::query()->lockForUpdate()->findOrFail($item['inventory_id']);
                if ($inventory->available_quantity < $item['requested_quantity']) {
                    throw ValidationException::withMessages([
                        count($items) === 1 ? 'requested_quantity' : 'items.'.$index.'.requested_quantity' => 'Requested quantity exceeds the available inventory quantity.',
                    ]);
                }
                $inventory->decrement('available_quantity', $item['requested_quantity']);
                $reservedInventories[$index] = $inventory;
            }

            $propertyRequest = PropertyRequestRecord::query()->create([
                'requested_by' => $request->user()->id,
                'request_type' => $type,
                'requested_item_name' => $summaryName,
                'requested_quantity' => $totalQuantity,
                'needed_by' => $request->date('needed_by'),
                'purpose' => trim((string) $request->input('purpose')),
                'additional_notes' => $this->normalizeText($request->input('additional_notes')),
                'issue_description' => $type === PropertyRequestRecord::TYPE_REPLACEMENT ? $this->normalizeText($request->input('issue_description')) : null,
                'replacement_assignment_id' => $replacementAssignment?->id,
                'inventory_id' => $items[0]['inventory_id'],
                'status' => PropertyRequestRecord::STATUS_PENDING,
            ]);

            $propertyRequest->update([
                'reference_no' => 'PR-'.now()->format('Y').'-'.str_pad((string) $propertyRequest->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($items as $index => $item) {
                PropertyRequestItem::query()->create([
                    'property_request_id' => $propertyRequest->id,
                    'property_id' => $item['property_id'],
                    'inventory_id' => $item['inventory_id'],
                    'requested_item_name' => $item['requested_item_name'],
                    'requested_quantity' => $item['requested_quantity'],
                ]);

                if (isset($reservedInventories[$index])) {
                    $inventory = $reservedInventories[$index]->refresh();
                    InventoryLog::create([
                        'inventory_id' => $inventory->id, 'property_id' => $inventory->property_id,
                        'property_request_id' => $propertyRequest->id, 'user_id' => $request->user()->id,
                        'action' => InventoryLog::ACTION_RESERVED, 'quantity_change' => -$item['requested_quantity'],
                        'available_after' => $inventory->available_quantity,
                        'notes' => 'Stock reserved for property request '.$propertyRequest->reference_no.'.',
                    ]);
                }
            }
        });

        return redirect()
            ->route('teacher.property-requests.index')
            ->with('success', 'Property request submitted successfully.');
    }

    public function index(Request $request): View
    {
        $propertyRequests = PropertyRequestRecord::query()
            ->with(['requester', 'processedBy', 'selectedProperty', 'replacementAssignment.property', 'inventory.property', 'items.property', 'items.approvedProperty'])
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
        $propertyRequest->ensureItems();
        $propertyRequest->load(['requester', 'processedBy', 'reviewedBy', 'assignment.property', 'selectedProperty', 'replacementAssignment.property', 'inventory.property', 'items.property', 'items.inventory.property', 'items.approvedProperty', 'items.assignment']);

        return view('property-requests.show', [
            'propertyRequest' => $propertyRequest,
            'statuses' => PropertyRequestRecord::statuses(),
            'properties' => Property::query()->with('availableUnits')->where('quantity', '>', 0)
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
            $record->ensureItems();
            $items = $record->items()->with(['property', 'inventory.property', 'approvedProperty'])->get();

            if ($request->user()->isStaff()) {
                if ($request->input('status') === PropertyRequestRecord::STATUS_FULFILLED) {
                    if ($record->status !== PropertyRequestRecord::STATUS_APPROVED || ! $record->reviewed_at || ! $record->processed_at || $record->assignment_id) {
                        throw ValidationException::withMessages(['status' => 'The admin must approve this request before staff can assign a property.']);
                    }

                    $assignments = [];
                    foreach ($items as $index => $item) {
                        if ($item->assignment_id) {
                            throw ValidationException::withMessages(['status' => 'This request has already been fulfilled.']);
                        }

                        $propertyId = $item->approved_property_id
                            ?: $item->property_id
                            ?: $item->inventory?->property_id
                            ?: ($items->count() === 1 ? $request->integer('property_id') : null);

                        if (! $propertyId) {
                            throw ValidationException::withMessages([
                                'items.'.$item->id.'.property_id' => 'Choose the property approved for this item before assigning.',
                            ]);
                        }

                        $unitIds = $request->input('items.'.$item->id.'.unit_ids');
                        $selectUnits = $request->boolean('items.'.$item->id.'.select_units');
                        if ($items->count() === 1 && $unitIds === null && $request->has('unit_ids')) {
                            $unitIds = $request->input('unit_ids', []);
                            $selectUnits = $request->boolean('select_units') || $request->has('unit_ids');
                        }

                        $assignment = app(RequestAssignmentService::class)->assign(
                            $record,
                            (int) $propertyId,
                            $request->user(),
                            $selectUnits || $unitIds !== null ? ($unitIds ?? []) : null,
                            $item->requested_quantity,
                            $item->requested_item_name,
                        );

                        $department = $request->input('items.'.$item->id.'.department')
                            ?: $request->input('department')
                            ?: $assignment->department;
                        $assignment->update(['department' => $department]);

                        $item->update([
                            'assignment_id' => $assignment->id,
                            'approved_property_id' => $assignment->property_id,
                        ]);
                        $assignments[] = $assignment;
                    }

                    $firstAssignment = $assignments[0] ?? null;
                    if (! $firstAssignment) {
                        throw ValidationException::withMessages(['status' => 'This request has no items to assign.']);
                    }

                    $record->update([
                        'status' => PropertyRequestRecord::STATUS_FULFILLED,
                        'assignment_id' => $firstAssignment->id,
                        'selected_property_id' => $firstAssignment->property_id,
                    ]);

                    foreach ($items->pluck('inventory_id')->filter()->unique() as $inventoryId) {
                        $inventory = Inventory::query()->find($inventoryId);
                        if ($inventory) {
                            InventoryLog::create([
                                'inventory_id' => $inventory->id, 'property_id' => $inventory->property_id,
                                'property_request_id' => $record->id, 'user_id' => $request->user()->id,
                                'action' => InventoryLog::ACTION_FULFILLED, 'quantity_change' => 0,
                                'available_after' => $inventory->available_quantity,
                                'notes' => 'Property request '.$record->reference_number.' fulfilled and assigned.',
                            ]);
                        }
                    }

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

            $approvedPropertyIds = [];
            if ($request->input('status') === PropertyRequestRecord::STATUS_APPROVED) {
                foreach ($items as $item) {
                    $propertyId = $item->inventory?->property_id
                        ?: $item->property_id
                        ?: $request->input('items.'.$item->id.'.property_id')
                        ?: ($items->count() === 1 ? $request->integer('property_id') : null);

                    $propertyKey = $items->count() === 1 ? 'property_id' : 'items.'.$item->id.'.property_id';

                    if (! $propertyId) {
                        throw ValidationException::withMessages([
                            $propertyKey => 'Choose the property that will be assigned for this item.',
                        ]);
                    }

                    $property = Property::query()->lockForUpdate()->find($propertyId);
                    if (! $property || $property->quantity < $item->requested_quantity
                        || in_array($property->status, [Property::STATUS_DISPOSED, Property::STATUS_FOR_DISPOSAL], true)) {
                        throw ValidationException::withMessages([
                            $propertyKey => 'Not enough available stock for '.$item->requested_item_name.'. Keep this request awaiting stock until inventory is available.',
                        ]);
                    }

                    $approvedPropertyIds[$item->id] = $property->id;
                }
            }

            if ($request->input('status') === PropertyRequestRecord::STATUS_REJECTED) {
                foreach ($items as $item) {
                    if (! $item->inventory_id) {
                        continue;
                    }
                    $inventory = Inventory::query()->lockForUpdate()->find($item->inventory_id);
                    if ($inventory) {
                        $inventory->increment('available_quantity', $item->requested_quantity);
                        $inventory->refresh();
                        InventoryLog::create([
                            'inventory_id' => $inventory->id, 'property_id' => $inventory->property_id,
                            'property_request_id' => $record->id, 'user_id' => $request->user()->id,
                            'action' => InventoryLog::ACTION_RELEASED, 'quantity_change' => $item->requested_quantity,
                            'available_after' => $inventory->available_quantity,
                            'notes' => 'Stock released after request rejection.',
                        ]);
                    }
                }
            }

            foreach ($items as $item) {
                $item->update([
                    'approved_property_id' => $approvedPropertyIds[$item->id] ?? null,
                ]);
            }

            $record->update([
                'status' => $request->input('status'),
                'selected_property_id' => $approvedPropertyIds ? reset($approvedPropertyIds) : null,
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
            'requestNumber' => $propertyRequest->reference_number,
            'backUrl' => $request->user()->isTeacher() ? route('teacher.property-requests.index') : route('property-requests.show', $propertyRequest),
        ]);
    }

    public function pdf(Request $request, PropertyRequestRecord $propertyRequest): Response
    {
        abort_unless(
            $request->user()->isAdmin()
            || $request->user()->isStaff()
            || $propertyRequest->requested_by === $request->user()->id,
            403
        );
        abort_if($request->user()->isAdmin() && ! $propertyRequest->reviewed_at, 403);

        $propertyRequest->ensureItems();
        $propertyRequest->load([
            'requester', 'reviewedBy', 'processedBy', 'items.property', 'items.inventory.property',
            'items.approvedProperty', 'items.assignment', 'replacementAssignment.property',
        ]);

        $pdf = Pdf::loadView('property-requests.pdf', [
            'propertyRequest' => $propertyRequest,
            'items' => $propertyRequest->items,
            'activeAdmin' => User::defaultAdmin(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('property-request-'.$propertyRequest->reference_number.'.pdf');
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
