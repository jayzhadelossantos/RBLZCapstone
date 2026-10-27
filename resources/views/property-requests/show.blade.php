@extends('layouts.app')

@section('title', 'Property Request | PARDS')
@section('page_title', 'Property Request')
@section('section_label', 'Staff Review and Admin Approval')

@section('content')
    <div class="dashboard-card p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <h2 class="h4">Request #{{ $propertyRequest->id }}: {{ $propertyRequest->requested_item_name }}</h2>
                <p class="mb-0">Requested by <strong>{{ $propertyRequest->requester?->name ?? 'Unavailable user' }}</strong></p>
                <div class="text-muted">{{ $propertyRequest->requester?->email }}</div>
            </div>
            <a href="{{ route('property-requests.index') }}" class="btn btn-outline-primary align-self-start">Back to Requests</a>
        </div>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="dashboard-card p-4">
                <h3 class="h5">Request Information</h3>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $propertyRequest->status_label }}</dd>
                    <dt class="col-sm-4">Request type</dt><dd class="col-sm-8">{{ $propertyRequest->request_type_label }}</dd>
                    <dt class="col-sm-4">Quantity</dt><dd class="col-sm-8">{{ $propertyRequest->requested_quantity }}</dd>
                    @if ($propertyRequest->request_type === \App\Models\PropertyRequestRecord::TYPE_REPLACEMENT)
                        <dt class="col-sm-4">Property to replace</dt><dd class="col-sm-8">{{ $propertyRequest->replacementAssignment?->property?->property_name ?? 'Original assignment unavailable' }}</dd>
                        <dt class="col-sm-4">Damage / issue</dt><dd class="col-sm-8">{{ $propertyRequest->issue_description }}</dd>
                    @endif
                    <dt class="col-sm-4">Needed by</dt><dd class="col-sm-8">{{ $propertyRequest->needed_by?->format('F d, Y') ?? 'Not specified' }}</dd>
                    <dt class="col-sm-4">Purpose</dt><dd class="col-sm-8">{{ $propertyRequest->purpose }}</dd>
                    <dt class="col-sm-4">Additional notes</dt><dd class="col-sm-8">{{ $propertyRequest->additional_notes ?: 'None' }}</dd>
                    <dt class="col-sm-4">Staff reviewer</dt><dd class="col-sm-8">{{ $propertyRequest->reviewedBy?->name ?? 'Awaiting review' }} {{ $propertyRequest->reviewed_at?->format('(M d, Y h:i A)') }}</dd>
                    <dt class="col-sm-4">Staff notes</dt><dd class="col-sm-8">{{ $propertyRequest->review_notes ?: 'None' }}</dd>
                    <dt class="col-sm-4">Admin decision by</dt><dd class="col-sm-8">{{ $propertyRequest->processedBy?->name ?? 'Awaiting decision' }}</dd>
                    <dt class="col-sm-4">Admin notes</dt><dd class="col-sm-8">{{ $propertyRequest->response_notes ?: 'None' }}</dd>
                </dl>
                @if ($propertyRequest->assignment_id)
                    <div class="alert alert-success mt-3" role="status">
                        {{ $propertyRequest->assignment?->property?->property_name }} has been assigned to {{ $propertyRequest->requester?->name }}. The receiving receipt is ready.
                    </div>
                    <a class="btn btn-primary" href="{{ route('property-requests.receipt', $propertyRequest) }}">Print Receiving Receipt</a>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="dashboard-card p-4">
                @php
                    $isStaff = auth()->user()->isStaff();
                    $canForward = $isStaff && $propertyRequest->status === 'pending';
                    $canAssign = $isStaff && $propertyRequest->status === 'approved' && $propertyRequest->reviewed_at && !$propertyRequest->assignment_id;
                    $canDecide = !$isStaff && in_array($propertyRequest->status, ['awaiting_admin', 'awaiting_stock', 'approved']) && !$propertyRequest->assignment_id;
                @endphp
                <h3 class="h5">{{ $canForward ? 'Review and Forward' : ($canAssign ? 'Assign Approved Property' : 'Admin Decision') }}</h3>
                @if ($canForward || $canAssign || $canDecide)
                    <form method="POST" action="{{ route('property-requests.status', $propertyRequest) }}" id="requestActionForm">
                        @csrf
                        @method('PATCH')
                        @if ($canDecide)
                            <label for="status" class="form-label">Decision</label>
                            <select id="status" name="status" class="form-select mb-3" required>
                                <option value="approved" @selected(old('status') === 'approved')>Approve for staff assignment</option>
                                <option value="awaiting_stock" @selected(old('status', $propertyRequest->status) === 'awaiting_stock')>Awaiting stock</option>
                                <option value="rejected" @selected(old('status') === 'rejected')>Reject request</option>
                            </select>
                        @else
                            <input type="hidden" id="status" name="status" value="{{ $canForward ? 'awaiting_admin' : 'fulfilled' }}">
                        @endif
                        @if ($canAssign || $canDecide)
                            <div id="propertySelection">
                                <label for="property_id" class="form-label">Select the requested property</label>
                                <select id="property_id" name="property_id" class="form-select">
                                    <option value="">Choose an inventory item</option>
                                    @foreach ($properties as $property)
                                        <option value="{{ $property->id }}" data-name="{{ $property->property_name }}" data-units="{{ $property->availableUnits->map(fn ($unit) => ['id' => $unit->id, 'serial_number' => $unit->serial_number])->toJson() }}" data-stock="{{ $property->quantity }}" data-department="{{ $property->department }}"
                                            @disabled($property->quantity < $propertyRequest->requested_quantity)
                                            @selected((string) old('property_id', $propertyRequest->selected_property_id) === (string) $property->id)>
                                            {{ $property->property_name }} - {{ $property->property_code }} ({{ $property->quantity }} available)
                                        </option>
                                    @endforeach
                                </select>
                                <div id="selectionMessage" class="alert alert-info mt-3" role="status" aria-live="polite" hidden></div>
                                @if ($canAssign && $properties->isEmpty())
                                    <div class="alert alert-warning">The approved property is no longer available. Ask the admin to mark this request as awaiting stock or approve a replacement.</div>
                                @endif
                            </div>
                        @endif
                        @unless ($canAssign)
                            <label for="response_notes" class="form-label">{{ $canForward ? 'Staff review notes' : 'Admin notes / reason' }}</label>
                            <textarea id="response_notes" name="response_notes" class="form-control" rows="3" maxlength="3000">{{ old('response_notes') }}</textarea>
                        @endunless
                        @if ($canAssign)
                            @include('assignments.partials.department-serials')
                            @include('assignments.partials.unit-picker', ['selectedUnitIds' => old('unit_ids', []), 'requiredUnitCount' => $propertyRequest->requested_quantity])
                        @endif
                        <button class="btn btn-primary w-100 mt-3" type="submit">{{ $canForward ? 'Forward to Admin' : ($canAssign ? 'Confirm Assignment and Create Receipt' : 'Save Admin Decision') }}</button>
                    </form>
                @else
                    <p class="text-muted mb-0">{{ $propertyRequest->status_label }}</p>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const requestForm = document.getElementById('requestActionForm');
    if (requestForm) {
        const decision = document.getElementById('status');
        const selection = document.getElementById('property_id');
        const notes = document.getElementById('response_notes');
        const updateSelection = () => {
            const needsProperty = ['approved', 'fulfilled'].includes(decision.value);
            if (selection) {
                selection.required = needsProperty;
                selection.disabled = !needsProperty;
                document.getElementById('propertySelection').hidden = !needsProperty;
                const option = selection.selectedOptions[0];
                const selectionMessage = document.getElementById('selectionMessage');
                selectionMessage.hidden = !option?.value;
                selectionMessage.textContent = option?.value
                    ? option.dataset.name + ': ' + option.dataset.stock + ' available. Requested quantity: {{ $propertyRequest->requested_quantity }}.'
                    : '';
            }
            if (notes) notes.required = ['rejected', 'awaiting_stock'].includes(decision.value);
        };
        decision.addEventListener('change', updateSelection);
        selection?.addEventListener('change', updateSelection);
        selection?.addEventListener('change', () => {
            const department = document.getElementById('department');
            if (department && !department.value.trim()) department.value = selection.selectedOptions[0]?.dataset.department || '';
        });
        updateSelection();
        requestForm.addEventListener('submit', (event) => {
            if (decision.value === 'fulfilled' && !window.confirm('Assign the selected property to this end-user and create the receiving receipt?')) event.preventDefault();
        });
    }
</script>
@endpush
