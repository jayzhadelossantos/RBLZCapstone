@extends('layouts.app')

@section('title', 'Property Request | PARDS')
@section('page_title', 'Property Request')
@section('section_label', 'Staff Review and Admin Approval')

@section('content')
    <div class="dashboard-card p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <h2 class="h4">Request {{ $propertyRequest->reference_number }}</h2>
                <p class="mb-0">Requested by <strong>{{ $propertyRequest->requester?->name ?? 'Unavailable user' }}</strong></p>
                <div class="text-muted">{{ $propertyRequest->requester?->email }} · Submitted {{ $propertyRequest->created_at?->format('F d, Y h:i A') }}</div>
            </div>
            <div class="d-flex align-items-start gap-2">
                <a href="{{ route('property-requests.pdf', $propertyRequest) }}" class="btn btn-outline-secondary">Download PDF</a>
                <a href="{{ route('property-requests.index') }}" class="btn btn-outline-primary">Back to Requests</a>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="dashboard-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h5 mb-0">Requested Properties</h3>
                    <span class="badge text-bg-light border text-dark">{{ $propertyRequest->items->count() }} item(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Property / Item</th>
                                <th>Requested</th>
                                <th>Approved Property</th>
                                <th>Assignment</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($propertyRequest->items as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->requested_item_name }}</div>
                                        <small class="text-muted">
                                            {{ $item->property?->property_code ?: ($item->inventory?->property?->property_code ?: 'Not in inventory') }}
                                        </small>
                                    </td>
                                    <td>{{ $item->requested_quantity }}</td>
                                    <td>{{ $item->approvedProperty?->property_name ?: 'Not yet selected' }}</td>
                                    <td>
                                        @if ($item->assignment)
                                            <a href="{{ route('assignments.show', $item->assignment) }}">Assignment #{{ $item->assignment->id }}</a>
                                        @else
                                            <span class="text-muted">Not assigned</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total quantity</th>
                                <th>{{ $propertyRequest->requested_quantity }}</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="dashboard-card p-4">
                <h3 class="h5">Request Information</h3>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Reference</dt><dd class="col-sm-8">{{ $propertyRequest->reference_number }}</dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $propertyRequest->status_label }}</dd>
                    <dt class="col-sm-4">Request type</dt><dd class="col-sm-8">{{ $propertyRequest->request_type_label }}</dd>
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
                        The approved properties have been assigned to {{ $propertyRequest->requester?->name }}. The receiving receipt is ready.
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
                <h3 class="h5">{{ $canForward ? 'Review and Forward' : ($canAssign ? 'Assign Approved Properties' : 'Admin Decision') }}</h3>
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
                                <label class="form-label">{{ $canAssign ? 'Approved properties for assignment' : 'Property to assign for each item' }}</label>
                                @foreach ($propertyRequest->items as $item)
                                    @php
                                        $fixedProperty = $item->inventory?->property ?? $item->property;
                                        $selectedPropertyId = $item->approved_property_id ?: $fixedProperty?->id;
                                    @endphp
                                    <div class="border rounded p-3 mb-2">
                                        <div class="fw-semibold">{{ $item->requested_item_name }} <span class="text-muted">× {{ $item->requested_quantity }}</span></div>
                                        @if ($fixedProperty && $canDecide)
                                            <input type="hidden" name="items[{{ $item->id }}][property_id]" value="{{ $fixedProperty->id }}">
                                            <div class="small text-muted mt-1">{{ $fixedProperty->property_name }} ({{ $fixedProperty->property_code }}) · {{ $fixedProperty->quantity }} available</div>
                                        @elseif ($canAssign)
                                            <input type="hidden" name="items[{{ $item->id }}][property_id]" value="{{ $selectedPropertyId }}">
                                            <div class="small mt-1">
                                                {{ $item->approvedProperty?->property_name ?? 'Property unavailable' }}
                                                @if ($item->approvedProperty)
                                                    <span class="text-muted">· {{ $item->approvedProperty->quantity }} available</span>
                                                @endif
                                            </div>
                                        @else
                                            <select name="items[{{ $item->id }}][property_id]" class="form-select mt-2 decision-property" required>
                                                <option value="">Choose a property</option>
                                                @foreach ($properties as $property)
                                                    <option value="{{ $property->id }}" @disabled($property->quantity < $item->requested_quantity) @selected((string) old('items.'.$item->id.'.property_id', $selectedPropertyId) === (string) $property->id)>
                                                        {{ $property->property_name }} - {{ $property->property_code }} ({{ $property->quantity }} available)
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">Needs {{ $item->requested_quantity }} item(s). Options with insufficient stock are disabled.</div>
                                        @endif
                                    </div>
                                @endforeach
                                @if ($canAssign && $propertyRequest->items->contains(fn ($item) => ! $item->approvedProperty && ! $item->property && ! $item->inventory?->property))
                                    <div class="alert alert-warning">One or more approved properties are no longer available. Ask the admin to select a replacement property or mark the request as awaiting stock.</div>
                                @endif
                            </div>
                        @endif

                        @unless ($canAssign)
                            <label for="response_notes" class="form-label">{{ $canForward ? 'Staff review notes' : 'Admin notes / reason' }}</label>
                            <textarea id="response_notes" name="response_notes" class="form-control" rows="3" maxlength="3000">{{ old('response_notes') }}</textarea>
                        @endunless

                        @if ($canAssign)
                            @if ($propertyRequest->items->count() === 1)
                                @include('assignments.partials.department-serials')
                                @php
                                    $singleItem = $propertyRequest->items->first();
                                    $singleProperty = $properties->firstWhere('id', $singleItem->approved_property_id ?: $singleItem->property_id);
                                @endphp
                                @if ($singleProperty && $singleProperty->availableUnits->isNotEmpty())
                                    <fieldset class="mt-3 mb-3">
                                        <legend class="h6">Available Units</legend>
                                        <input type="hidden" name="select_units" value="1">
                                        <div class="row g-2">
                                            @foreach ($singleProperty->availableUnits as $unit)
                                                <div class="col-sm-6">
                                                    <label class="border rounded-3 p-3 d-flex gap-2 align-items-center h-100">
                                                        <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" class="form-check-input m-0 flex-shrink-0" @checked(in_array($unit->id, old('unit_ids', [])))>
                                                        <span class="text-break">{{ $unit->serial_number }}</span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="form-text">Select exactly {{ $singleItem->requested_quantity }} unit(s).</div>
                                    </fieldset>
                                @endif
                            @else
                                <label for="department" class="form-label mt-3">Department</label>
                                <input type="text" id="department" name="department" class="form-control" value="{{ old('department') }}" maxlength="255">
                                <div class="form-text">Physical units will be assigned automatically for each approved property.</div>
                            @endif
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
        const propertyFields = Array.from(document.querySelectorAll('.decision-property'));
        const notes = document.getElementById('response_notes');
        const updateSelection = () => {
            const needsProperty = ['approved', 'fulfilled'].includes(decision.value);
            propertyFields.forEach((field) => {
                field.required = needsProperty;
                field.disabled = !needsProperty;
            });
            const selection = document.getElementById('propertySelection');
            if (selection && propertyFields.length) selection.hidden = !needsProperty;
            if (notes) notes.required = ['rejected', 'awaiting_stock'].includes(decision.value);
        };
        decision.addEventListener('change', updateSelection);
        updateSelection();
        requestForm.addEventListener('submit', (event) => {
            if (decision.value === 'fulfilled' && !window.confirm('Assign all approved properties to this end-user and create the receiving receipt?')) event.preventDefault();
        });
    }
</script>
@endpush
