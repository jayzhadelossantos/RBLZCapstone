<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequestSubmissionRequest;
use App\Http\Requests\UpdatePropertyRequestStatusRequest;
use App\Models\PropertyRequestRecord;
use App\Models\Property;
use App\Models\User;
use App\Models\Assignment;
use App\Services\RequestAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function create(): View
    {
        return view('teacher.property-requests.create', [
            'propertyRequest' => new PropertyRequestRecord(),
            'assignedProperties' => $this->activeAssignmentsFor(auth()->user()->id),
        ]);
    }

    public function store(StorePropertyRequestSubmissionRequest $request): RedirectResponse
    {
        $type = $request->input('request_type', PropertyRequestRecord::TYPE_NEW);
        $replacementAssignment = null;

        if ($type === PropertyRequestRecord::TYPE_REPLACEMENT) {
            $replacementAssignment = $this->activeAssignmentsFor($request->user()->id)
                ->firstWhere('id', $request->integer('replacement_assignment_id'));

            if (! $replacementAssignment) {
                throw ValidationException::withMessages(['replacement_assignment_id' => 'Select an active property assigned to you.']);
            }

            if ($request->integer('requested_quantity') > $replacementAssignment->quantity_assigned) {
                throw ValidationException::withMessages(['requested_quantity' => 'Replacement quantity cannot exceed the quantity assigned to you.']);
            }
        }

        PropertyRequestRecord::query()->create([
            'requested_by' => $request->user()->id,
            'request_type' => $type,
            'requested_item_name' => $replacementAssignment?->property?->property_name ?? trim((string) $request->input('requested_item_name')),
            'requested_quantity' => $request->integer('requested_quantity'),
            'needed_by' => $request->date('needed_by'),
            'purpose' => trim((string) $request->input('purpose')),
            'additional_notes' => $this->normalizeText($request->input('additional_notes')),
            'issue_description' => $type === PropertyRequestRecord::TYPE_REPLACEMENT ? $this->normalizeText($request->input('issue_description')) : null,
            'replacement_assignment_id' => $replacementAssignment?->id,
            'status' => PropertyRequestRecord::STATUS_PENDING,
        ]);

        return redirect()
            ->route('teacher.property-requests.index')
            ->with('success', 'Property request submitted successfully.');
    }

    public function index(Request $request): View
    {
        $propertyRequests = PropertyRequestRecord::query()
            ->with(['requester', 'processedBy', 'selectedProperty', 'replacementAssignment.property'])
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
        $propertyRequest->load(['requester', 'processedBy', 'reviewedBy', 'assignment.property', 'selectedProperty', 'replacementAssignment.property']);

        return view('property-requests.show', [
            'propertyRequest' => $propertyRequest,
            'statuses' => PropertyRequestRecord::statuses(),
            'properties' => Property::query()->with('availableUnits')->where('quantity', '>=', max(1, $propertyRequest->requested_quantity))
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
                $property = Property::query()->lockForUpdate()->findOrFail($request->integer('property_id'));
                if ($property->quantity < $record->requested_quantity || in_array($property->status, [Property::STATUS_DISPOSED, Property::STATUS_FOR_DISPOSAL], true)) {
                    throw ValidationException::withMessages(['property_id' => 'Not enough available stock. Keep this request awaiting stock until inventory is available.']);
                }
            }
            $record->update([
                'status' => $request->input('status'),
                'selected_property_id' => $request->input('status') === PropertyRequestRecord::STATUS_APPROVED ? $request->integer('property_id') : null,
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
}
