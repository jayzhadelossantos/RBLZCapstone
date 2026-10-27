@csrf

<div id="requestWizard">
    <div class="d-flex flex-wrap gap-2 mb-4" aria-label="Request steps">
        <span class="badge rounded-pill text-bg-primary px-3 py-2" data-step-indicator="1">1. Request type</span>
        <span class="badge rounded-pill text-bg-light border text-dark px-3 py-2" data-step-indicator="2">2. Item details</span>
        <span class="badge rounded-pill text-bg-light border text-dark px-3 py-2" data-step-indicator="3">3. Purpose and submit</span>
    </div>

    <div class="wizard-step" data-step="1">
        <h3 class="h5">What would you like to request?</h3>
        <label for="request_type" class="form-label">Request Type</label>
        <select id="request_type" name="request_type" class="form-select @error('request_type') is-invalid @enderror" required>
            <option value="new" @selected(old('request_type', $propertyRequest->request_type ?: 'new') === 'new')>Request a new property</option>
            <option value="replacement" @selected(old('request_type', $propertyRequest->request_type) === 'replacement')>Replacement for damaged or unusable property</option>
        </select>
        <div class="form-text">Choose replacement only for an item already assigned to you that is broken, unusable, or needs replacement.</div>
    </div>

    <div class="wizard-step" data-step="2" hidden>
        <div id="replacementDetails" hidden>
            <h3 class="h5">Replacement details</h3>
            <div class="border rounded p-3 bg-light-subtle">
                <label for="replacement_assignment_id" class="form-label">Property to Replace</label>
                <select id="replacement_assignment_id" name="replacement_assignment_id" class="form-select @error('replacement_assignment_id') is-invalid @enderror">
                    <option value="">Choose your assigned property</option>
                    @foreach ($assignedProperties as $assignment)
                        <option value="{{ $assignment->id }}" data-name="{{ $assignment->property?->property_name }}" @selected((string) old('replacement_assignment_id', $propertyRequest->replacement_assignment_id) === (string) $assignment->id)>
                            {{ $assignment->property?->property_name }} ({{ $assignment->quantity_assigned }} assigned, {{ $assignment->property?->property_code }})
                        </option>
                    @endforeach
                </select>
                @error('replacement_assignment_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                <label for="issue_description" class="form-label mt-3">Damage / Issue Description</label>
                <textarea id="issue_description" name="issue_description" rows="3" maxlength="3000" class="form-control @error('issue_description') is-invalid @enderror" placeholder="Describe what is broken, unusable, missing, or needs replacement.">{{ old('issue_description', $propertyRequest->issue_description) }}</textarea>
                @error('issue_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div id="newRequestDetails">
            <h3 class="h5">Find an inventory item</h3>
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
                            <option value="{{ $property->property_name }} - {{ $property->property_code }} ({{ $property->quantity }} {{ $property->unit }} available)" data-id="{{ $property->id }}"></option>
                        @endforeach
                    </datalist>
                    <div class="form-text">Start typing to search available inventory, then choose an exact suggestion.</div>
                    @error('available_property_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div id="unavailableItemDetails" hidden>
                    <label for="requested_item_name" class="form-label">Item Needed but Not Available</label>
                    <input type="text" id="requested_item_name" name="requested_item_name" class="form-control @error('requested_item_name') is-invalid @enderror" value="{{ old('requested_item_name', $propertyRequest->requested_item_name) }}" placeholder="Enter the item you need">
                    <div class="form-text">Use this only if the item does not appear in the available inventory search.</div>
                    @error('requested_item_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="row mt-1">
            <div class="col-md-6">
                <label for="requested_quantity" class="form-label">Requested Quantity</label>
                <input type="number" id="requested_quantity" name="requested_quantity" min="1" class="form-control @error('requested_quantity') is-invalid @enderror" value="{{ old('requested_quantity', $propertyRequest->requested_quantity ?: 1) }}" required>
                @error('requested_quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="needed_by" class="form-label">Needed By</label>
                <input type="date" id="needed_by" name="needed_by" class="form-control @error('needed_by') is-invalid @enderror" value="{{ old('needed_by', optional($propertyRequest->needed_by)->format('Y-m-d')) }}">
            </div>
        </div>
    </div>

    <div class="wizard-step" data-step="3" hidden>
        <h3 class="h5">Why is this needed?</h3>
        <label for="purpose" class="form-label">Purpose</label>
        <textarea id="purpose" name="purpose" rows="4" class="form-control @error('purpose') is-invalid @enderror" required>{{ old('purpose', $propertyRequest->purpose) }}</textarea>
        @error('purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <label for="additional_notes" class="form-label mt-3">Additional Notes</label>
        <textarea id="additional_notes" name="additional_notes" rows="3" class="form-control @error('additional_notes') is-invalid @enderror">{{ old('additional_notes', $propertyRequest->additional_notes) }}</textarea>
        <div class="alert alert-info mt-3 mb-0">Review the details, then submit. You can track staff and admin review in Request Tracking.</div>
    </div>

    <div class="d-flex justify-content-between gap-2 mt-4">
        <a href="{{ route('teacher.property-requests.index') }}" class="btn btn-light border">Cancel</a>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" id="wizardBack" hidden>Back</button>
            <button type="button" class="btn btn-primary" id="wizardNext">Continue</button>
            <button type="submit" class="btn btn-primary" id="wizardSubmit" hidden>{{ $submitLabel }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const wizard = document.getElementById('requestWizard');
    const requestForm = wizard.closest('form');
    const requestType = document.getElementById('request_type');
    const replacementDetails = document.getElementById('replacementDetails');
    const newRequestDetails = document.getElementById('newRequestDetails');
    const replacementAssignment = document.getElementById('replacement_assignment_id');
    const issueDescription = document.getElementById('issue_description');
    const requestSource = document.getElementById('request_source');
    const inventoryItemDetails = document.getElementById('inventoryItemDetails');
    const unavailableItemDetails = document.getElementById('unavailableItemDetails');
    const inventorySearch = document.getElementById('available_property_search');
    const inventoryPropertyId = document.getElementById('available_property_id');
    const requestedItem = document.getElementById('requested_item_name');
    const inventoryOptions = Array.from(document.querySelectorAll('#available_properties option'));
    const steps = Array.from(wizard.querySelectorAll('.wizard-step'));
    const backButton = document.getElementById('wizardBack');
    const nextButton = document.getElementById('wizardNext');
    const submitButton = document.getElementById('wizardSubmit');
    let currentStep = 1;

    const toggleRequestSource = () => {
        const isReplacement = requestType.value === 'replacement';
        const needsManualRequest = requestSource.value === 'unavailable';
        inventoryItemDetails.hidden = needsManualRequest || isReplacement;
        unavailableItemDetails.hidden = !needsManualRequest || isReplacement;
        inventorySearch.required = !isReplacement && !needsManualRequest;
        inventorySearch.disabled = isReplacement || needsManualRequest;
        inventoryPropertyId.disabled = isReplacement;
        requestedItem.required = !isReplacement && needsManualRequest;
        requestedItem.disabled = isReplacement || !needsManualRequest;
    };
    const toggleRequestType = () => {
        const isReplacement = requestType.value === 'replacement';
        replacementDetails.hidden = !isReplacement;
        newRequestDetails.hidden = isReplacement;
        replacementAssignment.required = isReplacement;
        replacementAssignment.disabled = !isReplacement;
        issueDescription.required = isReplacement;
        issueDescription.disabled = !isReplacement;
        requestSource.disabled = isReplacement;
        toggleRequestSource();
    };
    const updateInventorySelection = () => {
        const option = inventoryOptions.find((item) => item.value === inventorySearch.value);
        inventoryPropertyId.value = option?.dataset.id || '';
    };
    const showStep = (step) => {
        currentStep = step;
        steps.forEach((panel) => panel.hidden = Number(panel.dataset.step) !== step);
        wizard.querySelectorAll('[data-step-indicator]').forEach((indicator) => {
            const active = Number(indicator.dataset.stepIndicator) === step;
            indicator.className = `badge rounded-pill px-3 py-2 ${active ? 'text-bg-primary' : 'text-bg-light border text-dark'}`;
        });
        backButton.hidden = step === 1;
        nextButton.hidden = step === steps.length;
        submitButton.hidden = step !== steps.length;
    };
    const validateCurrentStep = () => {
        const invalidField = Array.from(steps[currentStep - 1].querySelectorAll('input, select, textarea'))
            .filter((field) => !field.disabled)
            .find((field) => !field.checkValidity());
        if (invalidField) {
            invalidField.reportValidity();
            return false;
        }
        return true;
    };

    requestType.addEventListener('change', toggleRequestType);
    requestSource.addEventListener('change', toggleRequestSource);
    inventorySearch.addEventListener('input', updateInventorySelection);
    nextButton.addEventListener('click', () => validateCurrentStep() && showStep(currentStep + 1));
    backButton.addEventListener('click', () => showStep(currentStep - 1));
    requestForm.addEventListener('submit', (event) => {
        for (let step = 1; step <= steps.length; step++) {
            showStep(step);
            if (!validateCurrentStep()) {
                event.preventDefault();
                return;
            }
        }
    });

    toggleRequestType();
    showStep({{ $errors->any() ? 3 : 1 }});
</script>
@endpush
