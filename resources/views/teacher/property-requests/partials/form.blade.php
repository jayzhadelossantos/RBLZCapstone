@csrf

<div class="row g-4">
    <div class="col-12">
        <label for="request_type" class="form-label">Request Type</label>
        <select id="request_type" name="request_type" class="form-select @error('request_type') is-invalid @enderror" required>
            <option value="new" @selected(old('request_type', $propertyRequest->request_type ?: 'new') === 'new')>Request a new property</option>
            <option value="replacement" @selected(old('request_type', $propertyRequest->request_type) === 'replacement')>Replacement for damaged or unusable property</option>
        </select>
        <div class="form-text">Use a replacement request when an assigned item is broken, no longer usable, or needs to be replaced.</div>
    </div>

    <div class="col-12" id="replacementDetails" hidden>
        <div class="border rounded p-3 bg-light-subtle">
            <label for="replacement_assignment_id" class="form-label">Property to Replace</label>
            <select id="replacement_assignment_id" name="replacement_assignment_id" class="form-select @error('replacement_assignment_id') is-invalid @enderror">
                <option value="">Choose your assigned property</option>
                @foreach ($assignedProperties as $assignment)
                    <option value="{{ $assignment->id }}" data-name="{{ $assignment->property?->property_name }}" data-quantity="{{ $assignment->quantity_assigned }}" @selected((string) old('replacement_assignment_id', $propertyRequest->replacement_assignment_id) === (string) $assignment->id)>
                        {{ $assignment->property?->property_name }} ({{ $assignment->quantity_assigned }} assigned, {{ $assignment->property?->property_code }})
                    </option>
                @endforeach
            </select>
            @error('replacement_assignment_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            @if ($assignedProperties->isEmpty())
                <div class="alert alert-warning mt-3 mb-0">You have no active assigned property available for replacement.</div>
            @endif

            <label for="issue_description" class="form-label mt-3">Damage / Issue Description</label>
            <textarea id="issue_description" name="issue_description" rows="3" maxlength="3000" class="form-control @error('issue_description') is-invalid @enderror" placeholder="Describe what is broken, unusable, missing, or needs replacement.">{{ old('issue_description', $propertyRequest->issue_description) }}</textarea>
            @error('issue_description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12" id="newRequestDetails">
        <div class="border rounded p-3 bg-light-subtle">
            <label for="request_source" class="form-label">Item Availability</label>
            <select id="request_source" name="request_source" class="form-select mb-3 @error('request_source') is-invalid @enderror">
                <option value="inventory" @selected(old('request_source', 'inventory') === 'inventory')>Choose from available inventory</option>
                <option value="unavailable" @selected(old('request_source') === 'unavailable')>Item is not available / not in the inventory list</option>
            </select>

            <div id="inventoryItemDetails">
                <label for="available_property_search" class="form-label">Available Inventory Item</label>
                <input id="available_property_search" list="available_properties" class="form-control @error('available_property_id') is-invalid @enderror" placeholder="Search by item name or property code" autocomplete="off">
                <input type="hidden" id="available_property_id" name="available_property_id" value="{{ old('available_property_id') }}">
                <datalist id="available_properties">
                    @foreach ($availableProperties as $property)
                        <option value="{{ $property->property_name }} — {{ $property->property_code }} ({{ $property->quantity }} {{ $property->unit }} available)" data-id="{{ $property->id }}" data-name="{{ $property->property_name }}" data-quantity="{{ $property->quantity }}"></option>
                    @endforeach
                </datalist>
                <div class="form-text">Start typing to search the currently available inventory. Select an exact suggestion.</div>
                @error('available_property_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                @if ($availableProperties->isEmpty())
                    <div class="alert alert-warning mt-3 mb-0">No inventory items are currently available. Choose the unavailable-item request option below.</div>
                @endif
            </div>

            <div id="unavailableItemDetails" hidden>
                <label for="requested_item_name" class="form-label">Item Needed but Not Available</label>
                <input type="text" id="requested_item_name" name="requested_item_name" class="form-control @error('requested_item_name') is-invalid @enderror" value="{{ old('requested_item_name', $propertyRequest->requested_item_name) }}" placeholder="Enter the item you need">
                <div class="form-text">Use this only if the item does not appear in the available inventory search.</div>
                @error('requested_item_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <label for="requested_quantity" class="form-label">Requested Quantity</label>
        <input
            type="number"
            id="requested_quantity"
            name="requested_quantity"
            min="1"
            class="form-control @error('requested_quantity') is-invalid @enderror"
            value="{{ old('requested_quantity', $propertyRequest->requested_quantity ?: 1) }}"
            required
        >
        @error('requested_quantity')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="needed_by" class="form-label">Needed By</label>
        <input
            type="date"
            id="needed_by"
            name="needed_by"
            class="form-control @error('needed_by') is-invalid @enderror"
            value="{{ old('needed_by', optional($propertyRequest->needed_by)->format('Y-m-d')) }}"
        >
        @error('needed_by')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="purpose" class="form-label">Purpose</label>
        <textarea
            id="purpose"
            name="purpose"
            rows="4"
            class="form-control @error('purpose') is-invalid @enderror"
            required
        >{{ old('purpose', $propertyRequest->purpose) }}</textarea>
        @error('purpose')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="additional_notes" class="form-label">Additional Notes</label>
        <textarea
            id="additional_notes"
            name="additional_notes"
            rows="3"
            class="form-control @error('additional_notes') is-invalid @enderror"
        >{{ old('additional_notes', $propertyRequest->additional_notes) }}</textarea>
        @error('additional_notes')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('teacher.property-requests.index') }}" class="btn btn-light border">Cancel</a>
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
</div>

@push('scripts')
<script>
    const requestType = document.getElementById('request_type');
    const replacementDetails = document.getElementById('replacementDetails');
    const newRequestDetails = document.getElementById('newRequestDetails');
    const replacementAssignment = document.getElementById('replacement_assignment_id');
    const requestedItem = document.getElementById('requested_item_name');
    const issueDescription = document.getElementById('issue_description');
    const requestSource = document.getElementById('request_source');
    const inventoryItemDetails = document.getElementById('inventoryItemDetails');
    const unavailableItemDetails = document.getElementById('unavailableItemDetails');
    const inventorySearch = document.getElementById('available_property_search');
    const inventoryPropertyId = document.getElementById('available_property_id');
    const inventoryOptions = Array.from(document.querySelectorAll('#available_properties option'));

    const toggleReplacement = () => {
        const isReplacement = requestType.value === 'replacement';
        replacementDetails.hidden = !isReplacement;
        newRequestDetails.hidden = isReplacement;
        replacementAssignment.required = isReplacement;
        issueDescription.required = isReplacement;
        requestedItem.required = !isReplacement && requestSource.value === 'unavailable';
        if (isReplacement) setReplacementItem();
        if (!isReplacement) toggleRequestSource();
    };
    const setReplacementItem = () => {
        const option = replacementAssignment.selectedOptions[0];
        if (option?.value) requestedItem.value = option.dataset.name;
    };
    const updateInventorySelection = () => {
        const option = inventoryOptions.find((item) => item.value === inventorySearch.value);
        inventoryPropertyId.value = option?.dataset.id || '';
    };
    const toggleRequestSource = () => {
        const needsManualRequest = requestSource.value === 'unavailable';
        inventoryItemDetails.hidden = needsManualRequest;
        unavailableItemDetails.hidden = !needsManualRequest;
        inventorySearch.required = !needsManualRequest;
        requestedItem.required = needsManualRequest;
        if (needsManualRequest) inventoryPropertyId.value = '';
    };

    requestType.addEventListener('change', toggleReplacement);
    replacementAssignment.addEventListener('change', setReplacementItem);
    requestSource.addEventListener('change', toggleRequestSource);
    inventorySearch.addEventListener('input', updateInventorySelection);
    toggleReplacement();
</script>
@endpush
